<?php

namespace App\Services\AdSync;

use App\Models\AdAccountConnection;
use App\Models\AdCampaign;
use App\Models\CampaignLog;
use App\Models\ClientAdBudget;
use App\Models\OrganizationIntegration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Ações na campanha direto da Central (etapa 4): pausar, reativar e ajustar a
 * verba diária — na Meta (campanha com CBO ou conjunto a conjunto) e no
 * Google (orçamento da campanha).
 *
 * Regras:
 * - toda ação pede um "por quê" e vai para o diário da campanha;
 * - AUMENTO de verba passa pelo teto da verba do mês do cliente (projeção:
 *   gasto do mês + ritmo atual + aumento × dias que faltam); redução e pausa
 *   são sempre liberadas;
 * - verba total (lifetime) e orçamento compartilhado do Google não são
 *   editados por aqui (só no gerenciador).
 * Validado em 09/11/2026: Meta validate_only, Google validateOnly.
 */
class CampaignControls
{
    public bool $validateOnly = false;

    private ?string $metaToken = null;

    public function __construct(private GoogleAdsApi $google) {}

    /**
     * Situação ao vivo: status e onde está a verba.
     *
     * @return array{can_edit: bool, status: ?string, budgets: array<int, array{level: string, id: string, name: string, status: ?string, daily: ?float, lifetime: ?float, editable: bool, why_not: ?string}>, error: ?string}
     */
    public function state(AdCampaign $campaign): array
    {
        $campaign->loadMissing('adAccount');
        $canEdit = AdAccountConnection::where('client_ad_account_id', $campaign->client_ad_account_id)->value('can_edit') !== false;

        try {
            $data = str_starts_with($campaign->platform, 'meta') ? $this->metaState($campaign) : $this->googleState($campaign);
        } catch (\Throwable $e) {
            return ['can_edit' => $canEdit, 'status' => null, 'budgets' => [], 'error' => $e->getMessage()];
        }

        return $data + ['can_edit' => $canEdit, 'error' => null];
    }

    /** @return ?string erro, ou null se deu certo */
    public function setStatus(AdCampaign $campaign, bool $active, string $reason, int $userId): ?string
    {
        if (str_starts_with($campaign->platform, 'meta')) {
            $res = $this->metaPost($campaign->external_id, ['status' => $active ? 'ACTIVE' : 'PAUSED']);
            $error = isset($res['error']) ? $this->metaError($res) : null;
        } else {
            [$cid, $state] = [$this->googleCid($campaign), $this->googleState($campaign)];
            $res = $this->googleMutate($cid, 'campaigns', [
                'update' => ['resourceName' => $state['resource'], 'status' => $active ? 'ENABLED' : 'PAUSED'], 'updateMask' => 'status',
            ]);
            $error = $res['error'];
        }

        if ($error || $this->validateOnly) {
            return $error;
        }

        $campaign->update(['status' => $active ? 'active' : 'paused']);
        $this->log($campaign, $active ? 'resume' : 'pause', ($active ? 'Campanha reativada' : 'Campanha pausada') . " pela Central. Por quê: {$reason}", $userId);

        return null;
    }

    /**
     * Muda a verba diária de uma campanha (CBO / Google) ou de um conjunto.
     *
     * @return ?string erro, ou null se deu certo
     */
    public function setDailyBudget(AdCampaign $campaign, string $level, string $targetId, float $newDaily, string $reason, int $userId): ?string
    {
        $state = $this->state($campaign);
        $target = collect($state['budgets'])->first(fn ($b) => $b['level'] === $level && $b['id'] === $targetId);
        if (!$target || !$target['editable']) {
            return $target['why_not'] ?? 'Verba não encontrada para editar.';
        }

        $old = (float) $target['daily'];
        if ($newDaily > $old && ($cap = $this->capCheck($campaign, $newDaily - $old))) {
            return $cap;
        }

        if (str_starts_with($campaign->platform, 'meta')) {
            // Meta: valor em centavos da moeda da conta.
            $res = $this->metaPost($targetId, ['daily_budget' => (int) round($newDaily * 100)]);
            $error = isset($res['error']) ? $this->metaError($res) : null;
        } else {
            $res = $this->googleMutate($this->googleCid($campaign), 'campaignBudgets', [
                'update' => ['resourceName' => $targetId, 'amountMicros' => (string) (int) round($newDaily * 1_000_000)], 'updateMask' => 'amount_micros',
            ]);
            $error = $res['error'];
        }

        if ($error || $this->validateOnly) {
            return $error;
        }

        $money = fn ($v) => 'R$ ' . number_format($v, 2, ',', '.');
        $where = $level === 'adset' ? "do conjunto \"{$target['name']}\"" : 'da campanha';
        $this->log($campaign, 'budget_change', "Verba diária {$where}: {$money($old)} → {$money($newDaily)} pela Central. Por quê: {$reason}", $userId, [
            'level' => $level, 'target' => $targetId, 'from' => $old, 'to' => $newDaily,
        ]);

        return null;
    }

    /**
     * Teto do mês: o aumento cabe na verba do mês do cliente?
     *
     * @return ?string mensagem de bloqueio, ou null se cabe
     */
    public function capCheck(AdCampaign $campaign, float $dailyIncrease): ?string
    {
        return $this->capCheckForClient($campaign->adAccount->client_id, $dailyIncrease);
    }

    /** Mesmo teto, para verba nova (ex.: campanha criada pelo App). */
    public function capCheckForClient(string $clientId, float $dailyIncrease): ?string
    {
        $room = $this->budgetRoom($clientId);
        if (!$room) {
            return null; // sem verba do mês cadastrada, não há teto para conferir
        }

        $projected = $room['spent'] + ($room['pace'] + $dailyIncrease) * $room['days_left'];
        if ($projected <= $room['cap']) {
            return null;
        }

        $money = fn ($v) => 'R$ ' . number_format($v, 2, ',', '.');

        return "Passa da verba do mês do cliente ({$money($room['cap'])}): no ritmo atual o mês fecharia em {$money($projected)}. "
            . "Gasto até agora: {$money($room['spent'])}. Dá para aumentar no máximo {$money($room['room'])} por dia. "
            . 'Para ir além, a verba do mês precisa ser revista com o cliente (ficha do cliente → Orçamento).';
    }

    /**
     * Situação da verba do mês do cliente: teto, gasto, ritmo e quanto ainda
     * cabe por dia. Null quando não há verba do mês cadastrada.
     *
     * @return ?array{cap: float, spent: float, pace: float, days_left: int, room: float}
     */
    public function budgetRoom(string $clientId): ?array
    {
        $budget = ClientAdBudget::where('client_id', $clientId)->where('start_date', '<=', now())
            ->orderByDesc('start_date')->orderByDesc('created_at')->first();
        if (!$budget || (float) $budget->monthly_budget <= 0) {
            return null; // verba zerada = não cadastrada (ex.: Farmagnus em 10/11/2026)
        }

        $accountIds = DB::connection('pgsql')->table('client_ad_accounts')->where('client_id', $clientId)->pluck('id');
        $q = fn () => DB::connection('pgsql')->table('ad_daily_snapshots')->whereIn('client_ad_account_id', $accountIds)->where('entity_level', 'campaign');
        $spent = (float) $q()->where('snapshot_date', '>=', now()->startOfMonth()->toDateString())->sum('spend');
        // Ritmo = média dos 3 últimos dias completos (hoje ainda está pela metade).
        $pace = (float) $q()->where('snapshot_date', '>=', now()->subDays(3)->toDateString())
            ->where('snapshot_date', '<', now()->toDateString())->sum('spend') / 3;
        $daysLeft = now()->daysInMonth - now()->day + 1;
        $cap = (float) $budget->monthly_budget;

        return ['cap' => $cap, 'spent' => $spent, 'pace' => $pace, 'days_left' => $daysLeft, 'room' => max(0, ($cap - $spent) / $daysLeft - $pace)];
    }

    private function metaState(AdCampaign $campaign): array
    {
        $j = $this->metaGet($campaign->external_id, ['fields' => 'status,daily_budget,lifetime_budget,adsets.limit(50){id,name,status,daily_budget,lifetime_budget}']);
        if (isset($j['error'])) {
            throw new \RuntimeException($this->metaError($j));
        }

        $budgets = [];
        if (!empty($j['daily_budget']) || !empty($j['lifetime_budget'])) {
            $budgets[] = $this->budgetRow('campaign', $campaign->external_id, 'Campanha (verba no nível da campanha)', $j['status'] ?? null, $j['daily_budget'] ?? null, $j['lifetime_budget'] ?? null, 100);
        } else {
            foreach ($j['adsets']['data'] ?? [] as $s) {
                $budgets[] = $this->budgetRow('adset', $s['id'], $s['name'], $s['status'] ?? null, $s['daily_budget'] ?? null, $s['lifetime_budget'] ?? null, 100);
            }
        }

        return ['status' => $j['status'] ?? null, 'budgets' => $budgets];
    }

    private function googleState(AdCampaign $campaign): array
    {
        $cid = $this->googleCid($campaign);
        $row = $this->google->search($cid, 'SELECT campaign.resource_name, campaign.status, campaign_budget.resource_name, campaign_budget.amount_micros, campaign_budget.total_amount_micros, campaign_budget.explicitly_shared FROM campaign WHERE campaign.id = ' . (int) $campaign->external_id)[0]
            ?? throw new \RuntimeException('O Google não encontrou esta campanha.');
        $b = $row['campaignBudget'] ?? [];
        $shared = (bool) ($b['explicitlyShared'] ?? false);
        $daily = isset($b['amountMicros']) ? $b['amountMicros'] / 1_000_000 : null;

        return [
            'status'   => $row['campaign']['status'] ?? null,
            'resource' => $row['campaign']['resourceName'] ?? null,
            'budgets'  => [[
                'level' => 'campaign', 'id' => $b['resourceName'] ?? '', 'name' => 'Orçamento da campanha', 'status' => $row['campaign']['status'] ?? null,
                'daily' => $daily, 'lifetime' => isset($b['totalAmountMicros']) ? $b['totalAmountMicros'] / 1_000_000 : null,
                'editable' => $daily !== null && !$shared,
                'why_not'  => $shared ? 'Orçamento compartilhado entre campanhas: ajuste no Google Ads.' : ($daily === null ? 'Orçamento total (não diário): ajuste no Google Ads.' : null),
            ]],
        ];
    }

    private function budgetRow(string $level, string $id, string $name, ?string $status, $daily, $lifetime, int $unit): array
    {
        $daily = $daily !== null && (int) $daily > 0 ? $daily / $unit : null;
        $lifetime = $lifetime !== null && (int) $lifetime > 0 ? $lifetime / $unit : null;

        return [
            'level' => $level, 'id' => $id, 'name' => $name, 'status' => $status, 'daily' => $daily, 'lifetime' => $lifetime,
            'editable' => $daily !== null,
            'why_not'  => $daily === null ? ($lifetime ? 'Verba total (para o período todo): ajuste no Gerenciador.' : 'Sem verba própria.') : null,
        ];
    }

    private function log(AdCampaign $campaign, string $type, string $text, int $userId, array $meta = []): void
    {
        CampaignLog::create([
            'organization_id'      => $campaign->organization_id,
            'client_ad_account_id' => $campaign->client_ad_account_id,
            'entity_level'         => 'campaign',
            'entity_id'            => $campaign->external_id,
            'entity_name'          => $campaign->name,
            'platform'             => $campaign->platform,
            'logged_by'            => $userId,
            'type'                 => $type,
            'description'          => $text,
            'metadata'             => $meta + ['via' => 'central'],
        ]);
    }

    private function metaGet(string $path, array $query): array
    {
        return Http::timeout(20)->get('https://graph.facebook.com/' . MetaAdsFetcher::API_VERSION . "/{$path}", $query + ['access_token' => $this->metaToken()])->json() ?? [];
    }

    private function metaPost(string $path, array $data): array
    {
        if ($this->validateOnly) {
            $data['execution_options'] = json_encode(['validate_only']);
        }

        return Http::timeout(30)->asForm()->post('https://graph.facebook.com/' . MetaAdsFetcher::API_VERSION . "/{$path}", $data + ['access_token' => $this->metaToken()])->json() ?? [];
    }

    private function metaToken(): string
    {
        return $this->metaToken ??= OrganizationIntegration::where('provider', 'meta')->where('status', 'connected')->first()?->credentials['access_token']
            ?? throw new \RuntimeException('Integração com a Meta não está conectada.');
    }

    private function metaError(array $res): string
    {
        return (string) ($res['error']['error_user_msg'] ?? $res['error']['message'] ?? 'erro desconhecido');
    }

    private function googleCid(AdCampaign $campaign): string
    {
        return GoogleAdsApi::cid($campaign->adAccount);
    }

    /** @return array{error: ?string} */
    private function googleMutate(string $cid, string $service, array $operation): array
    {
        return $this->google->mutate($cid, $service, [$operation], $this->validateOnly);
    }
}

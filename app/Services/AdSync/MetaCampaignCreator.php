<?php

namespace App\Services\AdSync;

use App\Models\AdAccountConnection;
use App\Models\CampaignLog;
use App\Models\ClientAdAccount;
use App\Models\MetaCampaignCreation;
use App\Models\OrganizationIntegration;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Campanha completa na Meta pelo App (Central de Campanhas → Nova campanha).
 *
 * Monta campanha (verba diária na campanha — CBO) + conjunto (cidades com
 * raio, idade, gênero, Facebook + Instagram) + anúncio opcional com a arte
 * aprovada (via MetaAdCreator). Tudo nasce PAUSADO. A verba passa pelo teto
 * da verba do mês do cliente na criação e de novo no "Ativar".
 *
 * Tipos (pedido do Evandro em 10/11/2026): Mensagens (WhatsApp), Tráfego para
 * o site e Reconhecimento. Nomes no padrão do time, sem o [#número].
 * Imóveis (categoria especial HOUSING): sem idade/gênero e raio mínimo 25 km.
 * Validado em 10/11/2026 com validate_only (campanha) e conjuntos em
 * campanhas compatíveis.
 */
class MetaCampaignCreator
{
    public const MIN_RADIUS = 17;          // cidade + raio: 17 a 80 km
    public const MAX_RADIUS = 80;
    public const MIN_RADIUS_HOUSING = 25;  // imóveis: mínimo de 15 milhas

    public bool $validateOnly = false;

    private ?string $token = null;

    public function __construct(private MetaAdCreator $ads, private CampaignControls $controls) {}

    /** Busca de cidade para o público (Brasil). */
    public function searchCities(string $q): array
    {
        $rows = $this->get('search', ['type' => 'adgeolocation', 'q' => $q, 'location_types' => json_encode(['city']), 'country_code' => 'BR', 'limit' => 8])['data'] ?? [];

        return array_map(fn ($c) => ['key' => (string) $c['key'], 'name' => $c['name'], 'region' => $c['region'] ?? ''], $rows);
    }

    /** Nome da campanha no padrão do time. */
    public static function campaignName(string $kind, string $description): string
    {
        $k = MetaCampaignCreation::$kinds[$kind];
        $desc = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $description))) ?: 'CAMPANHA';

        return "[00][CBO][INT+EXT][{$k['tag']}][{$desc}][{$k['result']}]";
    }

    /** Nome do conjunto no padrão do time. */
    public static function adsetName(array $audience, bool $housing): string
    {
        $who = match (true) {
            $housing                          => 'HO + MU 18 A 65+',
            ($audience['gender'] ?? '') === 'homens'   => "HO {$audience['age_min']} A {$audience['age_max']}",
            ($audience['gender'] ?? '') === 'mulheres' => "MU {$audience['age_min']} A {$audience['age_max']}",
            default                           => "HO + MU {$audience['age_min']} A {$audience['age_max']}",
        };
        $where = collect($audience['cities'] ?? [])->pluck('name')->map(fn ($n) => mb_strtoupper($n))->implode(' + ');
        $where .= ($audience['radius'] ?? 0) ? " +{$audience['radius']}KM" : '';

        return "[00][FB+IG][AUTO][ABERTO][{$who}][{$where}]";
    }

    /**
     * Problemas no que o gestor preencheu (vazio = ok).
     *
     * @return array<int, string>
     */
    public static function problems(array $input): array
    {
        $p = [];
        $a = $input['audience'];
        if (empty($a['cities'])) {
            $p[] = 'Escolha pelo menos uma cidade.';
        }
        $r = (int) ($a['radius'] ?? 0);
        $min = $input['housing'] ? self::MIN_RADIUS_HOUSING : self::MIN_RADIUS;
        if ($r !== 0 && ($r < $min || $r > self::MAX_RADIUS)) {
            $p[] = "Raio entre {$min} e " . self::MAX_RADIUS . ' km (ou 0 para só a cidade).';
        }
        if ($input['housing'] && $r === 0) {
            $p[] = 'Campanha de imóveis precisa de raio de pelo menos ' . self::MIN_RADIUS_HOUSING . ' km.';
        }
        if (!$input['housing'] && ((int) $a['age_min'] < 18 || (int) $a['age_max'] > 65 || (int) $a['age_min'] > (int) $a['age_max'])) {
            $p[] = 'Idade entre 18 e 65 anos.';
        }
        if ((float) $input['daily_budget'] < 6) {
            $p[] = 'A verba diária mínima é R$ 6,00.';
        }
        if ($input['kind'] === 'trafego' && empty($input['link'])) {
            $p[] = 'Campanha de tráfego precisa do link do site.';
        }

        return $p;
    }

    /**
     * Cria campanha + conjunto (+ anúncio) PAUSADOS.
     *
     * @param array{kind: string, campaign_name: string, adset_name: string, daily_budget: float, housing: bool, start: ?string, audience: array, link: ?string, task_id: ?string, attachment_id: ?string, message: ?string, headline: ?string} $input
     */
    public function create(ClientAdAccount $account, array $input, int $userId): MetaCampaignCreation
    {
        $kind = MetaCampaignCreation::$kinds[$input['kind']];
        $conn = AdAccountConnection::where('client_ad_account_id', $account->id)->first();
        abort_unless($conn?->page_id, 422, 'Esta conta não tem Página liberada para o App (veja Contas e conexões).');

        $creation = new MetaCampaignCreation([
            'organization_id'      => $account->client?->organization_id,
            'client_id'            => $account->client_id,
            'client_ad_account_id' => $account->id,
            'kind'                 => $input['kind'],
            'campaign_name'        => $input['campaign_name'],
            'adset_name'           => $input['adset_name'],
            'daily_budget'         => $input['daily_budget'],
            'housing'              => $input['housing'],
            'audience'             => $input['audience'],
            'created_by'           => $userId,
        ]);

        if ($cap = $this->controls->capCheckForClient($account->client_id, (float) $input['daily_budget'])) {
            return $creation->fill(['status' => 'erro', 'error' => $cap]);
        }

        // 1) Campanha (verba diária na campanha)
        $camp = $this->post("act_{$account->account_id}/campaigns", [
            'name'                        => $input['campaign_name'],
            'objective'                   => $kind['objective'],
            'status'                      => 'PAUSED',
            'special_ad_categories'       => json_encode($input['housing'] ? ['HOUSING'] : []),
            'special_ad_category_country' => $input['housing'] ? json_encode(['BR']) : null,
            'daily_budget'                => (int) round($input['daily_budget'] * 100),
            'bid_strategy'                => 'LOWEST_COST_WITHOUT_CAP',
        ]);
        if (isset($camp['error'])) {
            return $this->fail($creation, 'A Meta recusou a campanha: ' . $this->errorText($camp));
        }
        if ($this->validateOnly) {
            return $creation->fill(['status' => 'pausado']); // conferência: só a campanha dá para validar sozinha
        }
        $creation->campaign_id = $camp['id'];

        // 2) Conjunto
        $set = $this->post("act_{$account->account_id}/adsets", array_filter([
            'name'              => $input['adset_name'],
            'campaign_id'       => $camp['id'],
            'status'            => 'PAUSED',
            'billing_event'     => 'IMPRESSIONS',
            'optimization_goal' => $kind['goal'],
            'destination_type'  => $kind['destination'],
            'promoted_object'   => $input['kind'] === 'trafego' ? null : json_encode(['page_id' => $conn->page_id]),
            'targeting'         => json_encode($this->targeting($input['audience'], $input['housing'])),
            'start_time'        => ($input['start'] ? \Illuminate\Support\Carbon::parse($input['start'] . ' 00:00', 'America/Sao_Paulo') : now())->toIso8601String(),
        ]));
        if (isset($set['error'])) {
            // não deixa campanha vazia para trás
            $this->delete($camp['id']);

            return $this->fail($creation->fill(['campaign_id' => null]), 'A Meta recusou o público/conjunto: ' . $this->errorText($set));
        }
        $creation->fill(['adset_id' => $set['id'], 'status' => 'pausado'])->save();
        $this->log($creation, "Campanha criada pelo App ({$kind['label']}), pausada. Verba diária R$ " . number_format($input['daily_budget'], 2, ',', '.')
            . '. Público: ' . $this->audienceText($input['audience'], $input['housing']) . '.', $userId);

        // 3) Anúncio (opcional) com a arte aprovada
        if (!empty($input['task_id']) && !empty($input['attachment_id'])) {
            $this->createAd($creation, $account, $input, $userId);
        }

        return $creation;
    }

    /** Liga campanha, conjunto e anúncio. Confere o teto de novo. */
    public function activate(MetaCampaignCreation $creation, int $userId): ?string
    {
        if ($cap = $this->controls->capCheckForClient($creation->client_id, (float) $creation->daily_budget)) {
            return $cap;
        }

        foreach ([$creation->campaign_id, $creation->adset_id] as $id) {
            $res = $this->post($id, ['status' => 'ACTIVE']);
            if (isset($res['error'])) {
                return $this->errorText($res);
            }
        }
        if ($creation->adCreation && $creation->adCreation->status === 'pausado') {
            if ($err = $this->ads->activate($creation->adCreation, $userId)) {
                return 'Campanha ligada, mas o anúncio não: ' . $err;
            }
        }

        $creation->update(['status' => 'ativo', 'activated_by' => $userId, 'activated_at' => now()]);
        $this->log($creation, 'Campanha ativada pelo App' . ($creation->adCreation ? ' (com o anúncio).' : ' (sem anúncio: crie em Criativos).'), $userId, 'resume');

        return null;
    }

    public function audienceText(array $a, bool $housing): string
    {
        $where = collect($a['cities'] ?? [])->pluck('name')->implode(', ') . (($a['radius'] ?? 0) ? " + {$a['radius']} km" : '');
        if ($housing) {
            return "{$where} · todas as idades e gêneros (imóveis)";
        }
        $who = match ($a['gender'] ?? '') { 'homens' => 'homens', 'mulheres' => 'mulheres', default => 'homens e mulheres' };

        return "{$where} · {$who} de {$a['age_min']} a {$a['age_max']} anos";
    }

    private function targeting(array $a, bool $housing): array
    {
        $t = [
            'geo_locations'        => ['cities' => array_map(fn ($c) => array_filter([
                'key' => (string) $c['key'], 'radius' => ($a['radius'] ?? 0) ?: null, 'distance_unit' => ($a['radius'] ?? 0) ? 'kilometer' : null,
            ]), $a['cities'])],
            'publisher_platforms'  => ['facebook', 'instagram'],
            'targeting_automation' => ['advantage_audience' => 0],
        ];
        if (!$housing) {
            $t['age_min'] = (int) $a['age_min'];
            $t['age_max'] = (int) $a['age_max'];
            $t['genders'] = match ($a['gender'] ?? '') { 'homens' => [1], 'mulheres' => [2], default => [1, 2] };
        }

        return $t;
    }

    private function createAd(MetaCampaignCreation $creation, ClientAdAccount $account, array $input, int $userId): void
    {
        try {
            $task = Task::findOrFail($input['task_id']);
            $media = TaskAttachment::where('task_id', $task->id)->where('is_deliverable', true)->findOrFail($input['attachment_id']);
            $this->ads->adsets($account, true); // conjunto acabou de nascer: lista nova
            $adset = collect($this->ads->adsets($account))->firstWhere('id', $creation->adset_id);
            $link = $input['link'] ?: ($adset['link'] ?? null);
            $caption = trim((string) ($task->latestApprovalRound?->caption ?? ''));
            $ad = $this->ads->create($task, $account, $media, [
                'adset_id' => $creation->adset_id,
                'message'  => $input['message'] ?: $caption,
                'headline' => $input['headline'] ?? null,
                'link'     => ($adset['needs_link'] ?? false) ? ($link ?: $account->client?->website ?: 'https://www.facebook.com/' . AdAccountConnection::where('client_ad_account_id', $account->id)->value('page_id')) : null,
                'cta'      => $input['kind'] === 'trafego' ? 'LEARN_MORE' : null,
            ], $userId, $caption !== '' && trim((string) $input['message']) !== $caption);

            $creation->update(['meta_ad_creation_id' => $ad->exists ? $ad->id : null, 'ad_error' => $ad->status === 'erro' ? $ad->error : null]);
        } catch (\Throwable $e) {
            report($e);
            $creation->update(['ad_error' => 'O anúncio não foi criado: ' . $e->getMessage() . ' Crie pela aba Criativos.']);
        }
    }

    private function fail(MetaCampaignCreation $creation, string $error): MetaCampaignCreation
    {
        $creation->fill(['status' => 'erro', 'error' => $error]);
        if (!$this->validateOnly) {
            $creation->save();
        }

        return $creation;
    }

    private function log(MetaCampaignCreation $creation, string $text, int $userId, string $type = 'note'): void
    {
        CampaignLog::create([
            'organization_id'      => $creation->organization_id,
            'client_ad_account_id' => $creation->client_ad_account_id,
            'entity_level'         => 'campaign',
            'entity_id'            => $creation->campaign_id,
            'entity_name'          => $creation->campaign_name,
            'platform'             => 'meta',
            'logged_by'            => $userId,
            'type'                 => $type,
            'description'          => $text,
            'metadata'             => ['via' => 'central', 'kind' => 'meta_campaign_creation', 'creation_id' => $creation->id],
        ]);
    }

    private function delete(string $id): void
    {
        Http::timeout(30)->delete('https://graph.facebook.com/' . MetaAdsFetcher::API_VERSION . "/{$id}", ['access_token' => $this->token()]);
    }

    private function get(string $path, array $query = []): array
    {
        return Http::timeout(30)->get('https://graph.facebook.com/' . MetaAdsFetcher::API_VERSION . '/' . $path, $query + ['access_token' => $this->token()])->json() ?? [];
    }

    private function post(string $path, array $data): array
    {
        if ($this->validateOnly) {
            $data['execution_options'] = json_encode(['validate_only']);
        }

        return Http::timeout(60)->asForm()->post('https://graph.facebook.com/' . MetaAdsFetcher::API_VERSION . '/' . $path, array_filter($data, fn ($v) => $v !== null) + ['access_token' => $this->token()])->json() ?? [];
    }

    private function token(): string
    {
        return $this->token ??= OrganizationIntegration::where('provider', 'meta')->where('status', 'connected')->first()?->credentials['access_token']
            ?? throw new \RuntimeException('Integração com a Meta não está conectada.');
    }

    private function errorText(array $res): string
    {
        return (string) ($res['error']['error_user_msg'] ?? $res['error']['message'] ?? 'erro desconhecido');
    }
}

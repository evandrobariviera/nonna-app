<?php

namespace App\Console\Commands;

use App\Models\CampaignInsight;
use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Services\AdSync\AdDataUpserter;
use App\Services\AdSync\GoogleAdsFetcher;
use App\Services\AdSync\MetaAdsFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAdPlatforms extends Command
{
    protected $signature = 'campaigns:sync-ad-platforms';

    protected $description = 'Busca campanhas e métricas de D-1 direto no Meta Ads e Google Ads e grava no App';

    public function handle(MetaAdsFetcher $meta, GoogleAdsFetcher $google, AdDataUpserter $upserter): int
    {
        $yesterday = now()->subDay()->toDateString();
        $organizations = Organization::whereIn('status', ['trial', 'active'])->get();

        $totalAccounts = 0;
        $totalFailures = 0;

        foreach ($organizations as $organization) {
            app()->instance('currentOrganization', $organization);

            $metaIntegration = OrganizationIntegration::where('organization_id', $organization->id)
                ->where('provider', 'meta')->where('status', 'connected')->first();
            $googleIntegration = OrganizationIntegration::where('organization_id', $organization->id)
                ->where('provider', 'google')->where('status', 'connected')->first();

            $metaToken = $metaIntegration?->credentials['access_token'] ?? null;
            $googleToken = $googleIntegration ? $google->ensureFreshToken($googleIntegration) : null;

            if (!$metaToken && !$googleToken) {
                continue;
            }

            $clientIds = Client::pluck('id');
            $accounts = ClientAdAccount::whereIn('client_id', $clientIds)->where('status', 'ativo')->get();

            // organization_id fica ligado via Tenantable (bootTenantable() lê
            // app('currentOrganization') na hora de criar) — por isso o check
            // de saldo roda aqui dentro, com o binding ainda apontando pra
            // esta organização, e não depois de sair do loop.

            foreach ($accounts as $account) {
                $totalAccounts++;
                try {
                    if (str_starts_with($account->platform, 'meta') && $metaToken) {
                        $campaigns = $meta->fetchCampaigns($account, $metaToken);
                        $upserter->upsertCampaigns($organization->id, $account->id, 'meta', $campaigns);

                        $snapshots = $meta->fetchInsights($account, $metaToken, $yesterday);
                        if (!empty($snapshots)) {
                            $upserter->upsertSnapshots($organization->id, $account->id, 'meta', $yesterday, $snapshots);
                        }

                        // Saldo só existe pra conta de anúncios de verdade (meta_ads),
                        // não pro Business Manager (meta_bm).
                        if ($account->platform === 'meta_ads') {
                            $balanceData = $meta->fetchAccountBalance($account, $metaToken);
                            $upserter->upsertAccountBalance($account, $balanceData);
                        }
                    } elseif (str_starts_with($account->platform, 'google') && $googleToken) {
                        $result = $google->fetchCampaignsAndMetrics($account, $googleToken, $googleIntegration->credentials, $yesterday);
                        $upserter->upsertCampaigns($organization->id, $account->id, 'google', $result['campaigns']);
                        if (!empty($result['snapshots'])) {
                            $upserter->upsertSnapshots($organization->id, $account->id, 'google', $yesterday, $result['snapshots']);
                            $this->debitLedgerBalance($account, $result['snapshots']);
                        }
                    }
                } catch (\Throwable $e) {
                    $totalFailures++;
                    Log::warning('SyncAdPlatforms: falha ao sincronizar conta', [
                        'account_id' => $account->id,
                        'platform'   => $account->platform,
                        'error'      => $e->getMessage(),
                    ]);

                    // Uma queda de conexão com o Postgres deixa a conexão pgsql presa em
                    // estado de transação; sem isso, todas as contas seguintes falham em
                    // cascata com "There is already an active transaction".
                    DB::purge('pgsql');
                }
            }

            $this->checkLowBalances($accounts);
        }

        $this->info("Contas processadas: {$totalAccounts} (falhas: {$totalFailures}).");

        return self::SUCCESS;
    }

    // Alerta determinístico de saldo baixo — não usa IA (diferente dos outros
    // kinds de CampaignInsight, gerados por campaigns:generate-insights), é só
    // saldo/custo_diário. Cria um insight "novo" quando entra na faixa crítica
    // e resolve automaticamente quando o saldo é reposto acima do limite.
    // Precisa rodar com app('currentOrganization') ainda apontando pra
    // organização das contas recebidas, porque Tenantable::bootTenantable()
    // usa esse binding pra preencher organization_id na criação.
    private const LOW_BALANCE_THRESHOLD_DAYS = 3;

    // "Livro-caixa" de saldo pra contas boleto/PIX fora do Meta (ver
    // ClientAdAccount::usesLedgerBalance()) — a plataforma não expõe saldo
    // por API, então debitamos o gasto real do dia que acabou de ser
    // sincronizado. O crédito (quando um boleto/PIX é enviado com valor)
    // acontece em ClientAdBillingDocumentController::store(). Só debita se já
    // existir um saldo inicial (balance !== null) — sem isso não temos de
    // onde partir, e a conta continua mostrando "—" até alguém informar o
    // saldo real uma primeira vez (manual) ou subir o primeiro boleto.
    private function debitLedgerBalance(ClientAdAccount $account, array $snapshots): void
    {
        if (!$account->usesLedgerBalance() || $account->balance === null) {
            return;
        }

        $daySpend = collect($snapshots)
            ->filter(fn ($s) => ($s['entity_level'] ?? null) === 'campaign')
            ->sum(fn ($s) => (float) ($s['spend'] ?? 0));

        if ($daySpend <= 0) {
            return;
        }

        $account->update([
            'balance'        => (float) $account->balance - $daySpend,
            'balance_source' => 'ledger',
        ]);
    }

    private function checkLowBalances(Collection $accounts): void
    {
        $accounts = $accounts
            ->filter(fn (ClientAdAccount $account) => $account->budget_automation_enabled
                && $account->balance !== null
                && $account->hasBillingTracking());

        foreach ($accounts as $account) {
            $daysRemaining = $account->daysRemaining();

            $openInsight = CampaignInsight::where('client_ad_account_id', $account->id)
                ->where('kind', 'saldo_baixo')
                ->where('status', '!=', 'resolvido')
                ->first();

            if ($daysRemaining !== null && $daysRemaining <= self::LOW_BALANCE_THRESHOLD_DAYS) {
                if (!$openInsight) {
                    CampaignInsight::create([
                        'client_id'            => $account->client_id,
                        'client_ad_account_id' => $account->id,
                        'kind'                 => 'saldo_baixo',
                        'severity'             => 'critico',
                        'status'               => 'novo',
                        'title'                => "Saldo acabando — {$account->platformLabel()}",
                        'summary'              => "Saldo atual R$ " . number_format((float) $account->balance, 2, ',', '.')
                            . ", restam aproximadamente {$daysRemaining} dia(s) no ritmo de gasto atual. Gerar novo boleto/PIX.",
                        'metrics_snapshot'     => [
                            'balance'        => (float) $account->balance,
                            'days_remaining' => $daysRemaining,
                            'daily_spend'    => $account->avgDailySpend(),
                        ],
                        'generated_at'         => now(),
                    ]);
                }
            } elseif ($openInsight) {
                $openInsight->update(['status' => 'resolvido', 'resolved_at' => now()]);
            }
        }
    }
}

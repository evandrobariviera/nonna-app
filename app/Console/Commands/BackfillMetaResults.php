<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Services\AdSync\AdDataUpserter;
use App\Services\AdSync\MetaAdsFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillMetaResults extends Command
{
    protected $signature = 'campaigns:backfill-meta-results {--days=22} {--account=}';

    protected $description = 'Reprocessa snapshots de insights do Meta Ads dos últimos N dias, aplicando a resolução de conversão por optimization_goal (não mexe em ad_campaigns/ad_adsets/ad_ads)';

    public function handle(MetaAdsFetcher $meta, AdDataUpserter $upserter): int
    {
        $days = (int) $this->option('days');
        $accountId = $this->option('account');
        $since = now()->subDays($days)->toDateString();
        $until = now()->subDay()->toDateString();

        $organizations = Organization::whereIn('status', ['trial', 'active'])->get();

        $totalAccounts = 0;
        $totalFailures = 0;

        foreach ($organizations as $organization) {
            app()->instance('currentOrganization', $organization);

            $metaIntegration = OrganizationIntegration::where('organization_id', $organization->id)
                ->where('provider', 'meta')->where('status', 'connected')->first();
            $metaToken = $metaIntegration?->credentials['access_token'] ?? null;

            if (!$metaToken) {
                continue;
            }

            $clientIds = Client::pluck('id');
            $accountsQuery = ClientAdAccount::whereIn('client_id', $clientIds)
                ->where('platform', 'like', 'meta%');

            // Com --account, ignora o filtro de status ativo — é pra permitir
            // reprocessar uma conta específica escolhida a dedo, mesmo que
            // esteja pausada.
            if ($accountId) {
                $accountsQuery->where('id', $accountId);
            } else {
                $accountsQuery->where('status', 'ativo');
            }

            $accounts = $accountsQuery->get();

            foreach ($accounts as $account) {
                $totalAccounts++;

                try {
                    $snapshots = $meta->fetchInsightsRange($account, $metaToken, $since, $until);

                    collect($snapshots)->groupBy('snapshot_date')->each(
                        fn ($dateSnapshots, $date) => $upserter->upsertSnapshots(
                            $organization->id,
                            $account->id,
                            'meta',
                            $date,
                            $dateSnapshots->all()
                        )
                    );

                    $this->info("Conta {$account->id} ({$account->platform}): " . count($snapshots) . ' snapshots reprocessados.');
                } catch (\Throwable $e) {
                    $totalFailures++;
                    Log::warning('BackfillMetaResults: falha ao reprocessar conta', [
                        'account_id' => $account->id,
                        'platform'   => $account->platform,
                        'error'      => $e->getMessage(),
                    ]);

                    // Mesma razão do SyncAdPlatforms: uma queda de conexão deixa a
                    // transação pgsql presa, derrubando as contas seguintes em cascata.
                    DB::purge('pgsql');
                }
            }
        }

        $this->info("Contas processadas: {$totalAccounts} (falhas: {$totalFailures}).");

        return self::SUCCESS;
    }
}

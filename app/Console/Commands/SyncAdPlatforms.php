<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Services\AdSync\AdDataUpserter;
use App\Services\AdSync\GoogleAdsFetcher;
use App\Services\AdSync\MetaAdsFetcher;
use Illuminate\Console\Command;
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
                    } elseif (str_starts_with($account->platform, 'google') && $googleToken) {
                        $result = $google->fetchCampaignsAndMetrics($account, $googleToken, $googleIntegration->credentials, $yesterday);
                        $upserter->upsertCampaigns($organization->id, $account->id, 'google', $result['campaigns']);
                        if (!empty($result['snapshots'])) {
                            $upserter->upsertSnapshots($organization->id, $account->id, 'google', $yesterday, $result['snapshots']);
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
        }

        $this->info("Contas processadas: {$totalAccounts} (falhas: {$totalFailures}).");

        return self::SUCCESS;
    }
}

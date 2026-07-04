<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Organization;
use App\Services\CampaignInsightService;
use Illuminate\Console\Command;

class GenerateCampaignInsights extends Command
{
    protected $signature = 'campaigns:generate-insights';

    protected $description = 'Analisa orçamento e desempenho das campanhas ativas e gera insights automáticos por cliente';

    public function handle(CampaignInsightService $service): int
    {
        $organizations = Organization::whereIn('status', ['trial', 'active'])->get();

        $totalInsights = 0;

        foreach ($organizations as $organization) {
            app()->instance('currentOrganization', $organization);

            $clients = Client::where('status', 'active')->get();

            foreach ($clients as $client) {
                $created = $service->generateForClient($client);
                $totalInsights += count($created);
            }
        }

        $this->info("Insights gerados: {$totalInsights} (organizações analisadas: {$organizations->count()}).");

        return self::SUCCESS;
    }
}

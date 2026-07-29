<?php

namespace Tests\Unit;

use App\Models\AdCampaign;
use App\Models\ClientAdAccount;
use Tests\TestCase;

class AdCampaignBenchmarkTest extends TestCase
{
    public function test_campaign_override_wins_over_account_default(): void
    {
        $account = new ClientAdAccount();
        $account->forceFill(['target_cost_per_result' => 50.0, 'target_roas' => 2.0]);

        $campaign = new AdCampaign();
        $campaign->forceFill(['target_cost_per_result' => 20.0, 'target_roas' => 5.0]);
        $campaign->setRelation('adAccount', $account);

        $this->assertSame(20.0, $campaign->resolveTargetCostPerResult());
        $this->assertSame(5.0, $campaign->resolveTargetRoas());
    }

    public function test_falls_back_to_account_default_when_campaign_has_no_override(): void
    {
        $account = new ClientAdAccount();
        $account->forceFill(['target_cost_per_result' => 50.0, 'target_roas' => 2.0]);

        $campaign = new AdCampaign();
        $campaign->setRelation('adAccount', $account);

        $this->assertSame(50.0, $campaign->resolveTargetCostPerResult());
        $this->assertSame(2.0, $campaign->resolveTargetRoas());
    }

    public function test_returns_null_when_neither_level_has_a_benchmark(): void
    {
        $account = new ClientAdAccount();

        $campaign = new AdCampaign();
        $campaign->setRelation('adAccount', $account);

        $this->assertNull($campaign->resolveTargetCostPerResult());
        $this->assertNull($campaign->resolveTargetRoas());
    }

    public function test_returns_null_when_there_is_no_ad_account_at_all(): void
    {
        $campaign = new AdCampaign();
        $campaign->setRelation('adAccount', null);

        $this->assertNull($campaign->resolveTargetCostPerResult());
        $this->assertNull($campaign->resolveTargetRoas());
    }
}

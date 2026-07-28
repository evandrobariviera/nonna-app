<?php

namespace Tests\Unit\AdSync;

use App\Models\ClientAdAccount;
use App\Services\AdSync\MetaAdsFetcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaAdsFetcherTest extends TestCase
{
    private function account(): ClientAdAccount
    {
        $account = new ClientAdAccount();
        $account->forceFill([
            'id'         => '11111111-1111-1111-1111-111111111111',
            'account_id' => '999',
            'platform'   => 'meta_ads',
        ]);

        return $account;
    }

    public function test_fetch_campaigns_groups_adsets_and_ads_into_nested_shape(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/adsets')) {
                return Http::response(['data' => [
                    [
                        'id' => 'as1', 'name' => 'Adset 1', 'status' => 'ACTIVE',
                        'campaign_id' => 'c1', 'daily_budget' => '5000', 'targeting' => ['age_min' => 18],
                    ],
                ]]);
            }

            if (str_contains($url, '/ads')) {
                return Http::response(['data' => [
                    [
                        'id' => 'ad1', 'name' => 'Ad Imagem', 'status' => 'ACTIVE', 'adset_id' => 'as1',
                        'creative' => ['id' => 'cr1', 'thumbnail_url' => 'https://x/thumb1.jpg'],
                    ],
                    [
                        'id' => 'ad2', 'name' => 'Ad Video', 'status' => 'ACTIVE', 'adset_id' => 'as1',
                        'creative' => ['id' => 'cr2', 'thumbnail_url' => 'https://x/thumb2.jpg', 'video_id' => 'v1'],
                    ],
                    [
                        'id' => 'ad3', 'name' => 'Ad Carrossel', 'status' => 'ACTIVE', 'adset_id' => 'as1',
                        'creative' => ['id' => 'cr3', 'object_story_spec' => ['link_data' => ['child_attachments' => [['name' => 'a']]]]],
                    ],
                    [
                        'id' => 'ad4', 'name' => 'Ad Sem Criativo', 'status' => 'ACTIVE', 'adset_id' => 'as1',
                    ],
                ]]);
            }

            if (str_contains($url, '/campaigns')) {
                return Http::response(['data' => [
                    ['id' => 'c1', 'name' => 'Campanha 1', 'status' => 'ACTIVE', 'objective' => 'CONVERSIONS'],
                ]]);
            }

            return Http::response([], 404);
        });

        $fetcher = new MetaAdsFetcher();
        $campaigns = $fetcher->fetchCampaigns($this->account(), 'token');

        $this->assertCount(1, $campaigns);
        $this->assertSame('c1', $campaigns[0]['external_id']);
        $this->assertSame('active', $campaigns[0]['status']);

        $this->assertCount(1, $campaigns[0]['adsets']);
        $adset = $campaigns[0]['adsets'][0];
        $this->assertSame('as1', $adset['external_id']);
        $this->assertSame(50.0, $adset['daily_budget']); // 5000 centavos -> 50 reais

        $ads = collect($adset['ads'])->keyBy('external_id');
        $this->assertSame('image', $ads['ad1']['creative_type']);
        $this->assertSame('https://x/thumb1.jpg', $ads['ad1']['creative_url']);
        $this->assertSame('video', $ads['ad2']['creative_type']);
        $this->assertSame('carousel', $ads['ad3']['creative_type']);
        $this->assertNull($ads['ad4']['creative_type']);
        $this->assertNull($ads['ad4']['creative_url']);
    }

    public function test_fetch_campaigns_follows_pagination(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/adsets') || str_contains($url, '/ads')) {
                return Http::response(['data' => []]);
            }

            if (str_contains($url, 'after=CURSOR1')) {
                return Http::response(['data' => [
                    ['id' => 'c2', 'name' => 'Campanha 2', 'status' => 'ACTIVE'],
                ]]);
            }

            if (str_contains($url, '/campaigns')) {
                return Http::response([
                    'data'   => [['id' => 'c1', 'name' => 'Campanha 1', 'status' => 'ACTIVE']],
                    'paging' => ['next' => 'https://graph.facebook.com/v20.0/act_999/campaigns?after=CURSOR1'],
                ]);
            }

            return Http::response([], 404);
        });

        $fetcher = new MetaAdsFetcher();
        $campaigns = $fetcher->fetchCampaigns($this->account(), 'token');

        $this->assertCount(2, $campaigns);
        $this->assertSame(['c1', 'c2'], collect($campaigns)->pluck('external_id')->all());
    }

    public function test_fetch_insights_isolates_failure_per_level(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            // 'level=ad' é substring de 'level=adset' — precisa checar o mais
            // específico primeiro pra não capturar a chamada de adset também.
            if (str_contains($url, 'level=adset')) {
                return Http::response(['data' => [
                    ['campaign_id' => 'c1', 'campaign_name' => 'Campanha 1', 'adset_id' => 'as1', 'adset_name' => 'Adset 1', 'spend' => '10'],
                ]]);
            }

            if (str_contains($url, 'level=campaign')) {
                return Http::response(['data' => [
                    ['campaign_id' => 'c1', 'campaign_name' => 'Campanha 1', 'spend' => '100'],
                ]]);
            }

            if (str_contains($url, 'level=ad')) {
                return Http::response(['error' => ['message' => 'rate limited']], 500);
            }

            return Http::response([], 404);
        });

        $fetcher = new MetaAdsFetcher();
        $snapshots = $fetcher->fetchInsights($this->account(), 'token', '2026-07-27');

        $byLevel = collect($snapshots)->groupBy('entity_level');
        $this->assertCount(1, $byLevel->get('campaign'));
        $this->assertCount(1, $byLevel->get('adset'));
        $this->assertNull($byLevel->get('ad'));

        $adsetRow = $byLevel->get('adset')->first();
        $this->assertSame('as1', $adsetRow['entity_id']);
        $this->assertSame('c1', $adsetRow['parent_entity_id']);
    }
}

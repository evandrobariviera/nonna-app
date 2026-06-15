<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdAd;
use App\Models\AdAdset;
use App\Models\AdCampaign;
use App\Models\AdDailySnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    // POST /api/sync/campaigns
    // n8n envia a estrutura de campanhas/adsets/ads (cria ou atualiza)
    public function campaigns(Request $request): JsonResponse
    {
        $org  = app('currentOrganization');
        $data = $request->validate([
            'client_ad_account_id' => ['required', 'uuid'],
            'platform'             => ['required', 'in:meta,google'],
            'campaigns'            => ['required', 'array'],
            'campaigns.*.external_id' => ['required', 'string'],
            'campaigns.*.name'        => ['required', 'string'],
            'campaigns.*.status'      => ['nullable', 'string'],
            'campaigns.*.objective'   => ['nullable', 'string'],
            'campaigns.*.start_date'  => ['nullable', 'date'],
            'campaigns.*.end_date'    => ['nullable', 'date'],
            'campaigns.*.raw_data'    => ['nullable', 'array'],
            'campaigns.*.adsets'      => ['nullable', 'array'],
        ]);

        $counts = ['campaigns' => 0, 'adsets' => 0, 'ads' => 0];

        DB::connection('pgsql')->transaction(function () use ($org, $data, &$counts) {
            foreach ($data['campaigns'] as $campaignData) {
                $campaign = AdCampaign::updateOrCreate(
                    [
                        'client_ad_account_id' => $data['client_ad_account_id'],
                        'external_id'          => $campaignData['external_id'],
                    ],
                    [
                        'organization_id' => $org->id,
                        'platform'        => $data['platform'],
                        'name'            => $campaignData['name'],
                        'status'          => $campaignData['status'] ?? 'active',
                        'objective'       => $campaignData['objective'] ?? null,
                        'start_date'      => $campaignData['start_date'] ?? null,
                        'end_date'        => $campaignData['end_date'] ?? null,
                        'raw_data'        => $campaignData['raw_data'] ?? null,
                        'last_synced_at'  => now(),
                    ]
                );
                $counts['campaigns']++;

                foreach ($campaignData['adsets'] ?? [] as $adsetData) {
                    $adset = AdAdset::updateOrCreate(
                        [
                            'ad_campaign_id' => $campaign->id,
                            'external_id'    => $adsetData['external_id'],
                        ],
                        [
                            'organization_id'      => $org->id,
                            'platform'             => $data['platform'],
                            'campaign_external_id' => $campaign->external_id,
                            'name'                 => $adsetData['name'],
                            'status'               => $adsetData['status'] ?? 'active',
                            'daily_budget'         => $adsetData['daily_budget'] ?? null,
                            'lifetime_budget'      => $adsetData['lifetime_budget'] ?? null,
                            'targeting'            => $adsetData['targeting'] ?? null,
                            'raw_data'             => $adsetData['raw_data'] ?? null,
                            'last_synced_at'       => now(),
                        ]
                    );
                    $counts['adsets']++;

                    foreach ($adsetData['ads'] ?? [] as $adData) {
                        AdAd::updateOrCreate(
                            [
                                'ad_adset_id' => $adset->id,
                                'external_id' => $adData['external_id'],
                            ],
                            [
                                'organization_id'    => $org->id,
                                'platform'           => $data['platform'],
                                'adset_external_id'  => $adset->external_id,
                                'name'               => $adData['name'],
                                'status'             => $adData['status'] ?? 'active',
                                'creative_type'      => $adData['creative_type'] ?? null,
                                'creative_url'       => $adData['creative_url'] ?? null,
                                'raw_data'           => $adData['raw_data'] ?? null,
                                'last_synced_at'     => now(),
                            ]
                        );
                        $counts['ads']++;
                    }
                }
            }
        });

        return response()->json([
            'message' => 'Campanhas sincronizadas.',
            'synced'  => $counts,
        ]);
    }

    // POST /api/sync/snapshots
    // n8n envia snapshots diários de métricas (campaign + adset + ad level)
    public function snapshots(Request $request): JsonResponse
    {
        $org  = app('currentOrganization');
        $data = $request->validate([
            'client_ad_account_id' => ['required', 'uuid'],
            'platform'             => ['required', 'in:meta,google'],
            'snapshot_date'        => ['required', 'date'],
            'snapshots'            => ['required', 'array', 'min:1'],
            'snapshots.*.entity_level'      => ['required', 'in:campaign,adset,ad'],
            'snapshots.*.entity_id'         => ['required', 'string'],
            'snapshots.*.entity_name'       => ['required', 'string'],
            'snapshots.*.parent_entity_id'  => ['nullable', 'string'],
            'snapshots.*.spend'             => ['nullable', 'numeric'],
            'snapshots.*.revenue'           => ['nullable', 'numeric'],
            'snapshots.*.impressions'       => ['nullable', 'integer'],
            'snapshots.*.clicks'            => ['nullable', 'integer'],
            'snapshots.*.conversions'       => ['nullable', 'integer'],
            'snapshots.*.reach'             => ['nullable', 'integer'],
            'snapshots.*.raw_data'          => ['nullable', 'array'],
        ]);

        $upserted = 0;

        DB::connection('pgsql')->transaction(function () use ($org, $data, &$upserted) {
            foreach ($data['snapshots'] as $snap) {
                $row = AdDailySnapshot::withCalculatedMetrics([
                    'organization_id'       => $org->id,
                    'client_ad_account_id'  => $data['client_ad_account_id'],
                    'platform'              => $data['platform'],
                    'snapshot_date'         => $data['snapshot_date'],
                    'entity_level'          => $snap['entity_level'],
                    'entity_id'             => $snap['entity_id'],
                    'entity_name'           => $snap['entity_name'],
                    'parent_entity_id'      => $snap['parent_entity_id'] ?? null,
                    'spend'                 => $snap['spend'] ?? 0,
                    'revenue'               => $snap['revenue'] ?? 0,
                    'impressions'           => $snap['impressions'] ?? 0,
                    'clicks'                => $snap['clicks'] ?? 0,
                    'conversions'           => $snap['conversions'] ?? 0,
                    'reach'                 => $snap['reach'] ?? 0,
                    'raw_data'              => $snap['raw_data'] ?? null,
                ]);

                AdDailySnapshot::updateOrCreate(
                    [
                        'client_ad_account_id' => $data['client_ad_account_id'],
                        'entity_level'         => $snap['entity_level'],
                        'entity_id'            => $snap['entity_id'],
                        'snapshot_date'        => $data['snapshot_date'],
                    ],
                    $row
                );
                $upserted++;
            }
        });

        return response()->json([
            'message'  => 'Snapshots salvos.',
            'upserted' => $upserted,
        ]);
    }
}

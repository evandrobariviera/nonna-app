<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdSync\AdDataUpserter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    // POST /api/sync/campaigns
    // Endpoint externo (n8n ou outra ferramenta) para enviar a estrutura de campanhas/adsets/ads.
    // A sincronização automática do App roda por dentro (app/Console/Commands/SyncAdPlatforms.php),
    // sem passar por aqui - este endpoint fica disponível como via alternativa/externa.
    public function campaigns(Request $request, AdDataUpserter $upserter): JsonResponse
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

        $counts = $upserter->upsertCampaigns($org->id, $data['client_ad_account_id'], $data['platform'], $data['campaigns']);

        return response()->json([
            'message' => 'Campanhas sincronizadas.',
            'synced'  => $counts,
        ]);
    }

    // POST /api/sync/snapshots
    public function snapshots(Request $request, AdDataUpserter $upserter): JsonResponse
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

        $upserted = $upserter->upsertSnapshots($org->id, $data['client_ad_account_id'], $data['platform'], $data['snapshot_date'], $data['snapshots']);

        return response()->json([
            'message'  => 'Snapshots salvos.',
            'upserted' => $upserted,
        ]);
    }
}

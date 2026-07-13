<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\CampaignLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CampaignLogController extends Controller
{
    public function store(Request $request, AdCampaign $campaign)
    {
        $types = array_diff(array_keys(CampaignLog::$types), ['otimizacao']);

        $data = $request->validate([
            'type'        => 'required|string|in:' . implode(',', $types),
            'description' => 'required|string|max:5000',
        ]);

        CampaignLog::create([
            'organization_id'      => $campaign->organization_id,
            'client_ad_account_id' => $campaign->client_ad_account_id,
            'entity_level'         => 'campaign',
            'entity_id'            => $campaign->external_id,
            'entity_name'          => $campaign->name,
            'platform'             => $campaign->platform,
            'logged_by'            => Auth::id(),
            'type'                 => $data['type'],
            'description'          => $data['description'],
        ]);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Registro adicionado.')
            ->withFragment('historico');
    }

    public function destroy(AdCampaign $campaign, CampaignLog $log)
    {
        abort_unless($log->entity_id === $campaign->external_id, 403);
        abort_unless($log->logged_by === Auth::id(), 403);

        $log->delete();

        return redirect()->route('campaigns.show', $campaign)->withFragment('historico');
    }
}

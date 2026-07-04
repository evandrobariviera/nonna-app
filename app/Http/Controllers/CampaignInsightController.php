<?php

namespace App\Http\Controllers;

use App\Models\CampaignInsight;
use Illuminate\Http\Request;

class CampaignInsightController extends Controller
{
    public function updateStatus(Request $request, CampaignInsight $insight)
    {
        $data = $request->validate([
            'status' => 'required|in:novo,lido,resolvido,descartado',
        ]);

        $update = ['status' => $data['status']];
        if (in_array($data['status'], ['resolvido', 'descartado'])) {
            $update['resolved_at'] = now();
            $update['resolved_by'] = auth()->id();
        }

        $insight->update($update);

        return back()->with('success', 'Insight atualizado.');
    }
}

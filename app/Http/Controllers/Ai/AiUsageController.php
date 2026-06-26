<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiTokenUsage;
use App\Models\AiAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiUsageController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->input('period', 'month'); // today | week | month | all

        $query = AiTokenUsage::query();

        $query = match($period) {
            'today' => $query->whereDate('created_at', today()),
            'week'  => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            default => $query,
        };

        // Totais gerais
        $totals = (clone $query)->selectRaw('
            SUM(total_tokens) as total_tokens,
            SUM(prompt_tokens) as prompt_tokens,
            SUM(completion_tokens) as completion_tokens,
            SUM(estimated_cost_usd) as total_cost,
            COUNT(*) as total_calls
        ')->first();

        // Por agente
        $byAgent = (clone $query)
            ->selectRaw('agent_id, SUM(total_tokens) as tokens, SUM(estimated_cost_usd) as cost, COUNT(*) as calls')
            ->groupBy('agent_id')
            ->with('agent:id,name')
            ->orderByDesc('cost')
            ->get();

        // Por modelo
        $byModel = (clone $query)
            ->selectRaw('model, provider, SUM(total_tokens) as tokens, SUM(estimated_cost_usd) as cost, COUNT(*) as calls')
            ->groupBy('model', 'provider')
            ->orderByDesc('cost')
            ->get();

        // Por trigger
        $byTrigger = (clone $query)
            ->selectRaw('trigger, SUM(total_tokens) as tokens, SUM(estimated_cost_usd) as cost, COUNT(*) as calls')
            ->groupBy('trigger')
            ->orderByDesc('cost')
            ->get();

        // Histórico recente
        $recent = (clone $query)
            ->with(['agent:id,name', 'user:id,name'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('ai.usage.index', compact(
            'totals', 'byAgent', 'byModel', 'byTrigger', 'recent', 'period'
        ));
    }
}

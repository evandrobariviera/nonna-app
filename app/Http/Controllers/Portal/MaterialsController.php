<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\TaskApprovalRound;
use Illuminate\View\View;

// Área dedicada só pra download — reúne os entregáveis de toda rodada já
// aprovada do cliente, separado do histórico geral de Aprovações (que mistura
// pending/ajustes/cancelado e não é o lugar natural pra "só quero baixar o
// arquivo que já foi aprovado").
class MaterialsController extends Controller
{
    public function index(): View
    {
        $client = app('currentPortalClient');

        $rounds = TaskApprovalRound::whereHas('task', fn ($q) => $q->where('client_id', $client->id))
            ->where('status', 'approved')
            ->with('task')
            ->orderByDesc('resolved_at')
            ->get()
            ->filter(fn (TaskApprovalRound $round) => $round->deliverables()->isNotEmpty());

        return view('portal.materials.index', compact('client', 'rounds'));
    }
}

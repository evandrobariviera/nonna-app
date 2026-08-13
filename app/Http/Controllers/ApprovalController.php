<?php

namespace App\Http\Controllers;

use App\Models\TaskApprovalToken;
use App\Services\TaskApprovalService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(private TaskApprovalService $service) {}

    public function show(string $token)
    {
        $approvalToken = TaskApprovalToken::where('token', $token)
            ->with([
                'round.task.client',
                'round.submittedBy',
                'round.tokens.contact',
                // Histórico de todas as rodadas da mesma tarefa (quem aprovou/pediu
                // ajuste em cada uma) — pra o aprovador ver o quadro completo, não só
                // a rodada atual.
                'round.task.approvalRounds.tokens.contact',
                'contact',
            ])
            ->firstOrFail();

        // Aviso não pede decisão — o token já nasce "approved" (ver
        // TaskApprovalService::sendAviso()), então isValid()/isPending() não
        // se aplicam; só a expiração importa pro link continuar acessível.
        $isAviso = $approvalToken->round->isAviso();

        if (! $isAviso && ! $approvalToken->isValid()) {
            return view('approval.expired', compact('approvalToken'));
        }

        if ($isAviso && $approvalToken->isExpired()) {
            return view('approval.expired', compact('approvalToken'));
        }

        $deliverables = $approvalToken->round->deliverables();
        $batch = $this->batchForContact($approvalToken);

        return view('approval.show', compact('approvalToken', 'deliverables', 'batch'));
    }

    /**
     * Outros jobs do mesmo contato + mesmo cliente, no mesmo mês da rodada
     * atual (mesmo agrupamento do "Calendário" da ferramenta antiga que a
     * agência usava) — vira a barra de navegação numerada no topo, pra ele
     * trocar de job sem precisar de outro link. Cada um mantém decisão
     * própria; isso é só navegação, não muda o fluxo de aprovação.
     *
     * @return \Illuminate\Support\Collection<int, TaskApprovalToken>
     */
    private function batchForContact(TaskApprovalToken $approvalToken)
    {
        $round = $approvalToken->round;

        return TaskApprovalToken::where('contact_id', $approvalToken->contact_id)
            ->whereHas('round', function ($q) use ($round) {
                $q->where('status', '!=', 'cancelled')
                    ->whereYear('submitted_at', $round->submitted_at->year)
                    ->whereMonth('submitted_at', $round->submitted_at->month)
                    ->whereHas('task', fn ($t) => $t->where('client_id', $round->task->client_id));
            })
            ->with('round.task')
            ->get()
            ->sortBy('created_at')
            ->values();
    }

    public function submit(Request $request, string $token)
    {
        $approvalToken = TaskApprovalToken::where('token', $token)->with('round')->firstOrFail();

        // Aviso não tem decisão a submeter — a página pública nem mostra o
        // formulário, isso é só defesa contra um POST manual.
        if ($approvalToken->round->isAviso() || ! $approvalToken->isValid()) {
            return redirect()->route('approval.show', $token);
        }

        $data = $request->validate([
            'decision' => 'required|in:approved,changes_requested',
            'comment'  => ['nullable', 'string', 'max:2000', 'required_if:decision,changes_requested'],
        ]);

        $this->service->submitDecision($approvalToken, $data['decision'], $data['comment'] ?? null);

        $approvalToken->refresh();

        return view('approval.thanks', ['approvalToken' => $approvalToken->load('round.task')]);
    }
}

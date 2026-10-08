<?php

namespace App\Http\Controllers;

use App\Models\TaskApprovalToken;
use App\Services\ApprovalReviewQueue;
use App\Services\PortalMagicAccess;
use App\Services\ProjectApprovalPageService;
use App\Services\TaskApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    public function __construct(
        private TaskApprovalService $service,
        private PortalMagicAccess $magic,
        private ProjectApprovalPageService $pages,
        private ApprovalReviewQueue $queue,
    ) {}

    /**
     * Abrir o link entra na Central de Aprovações do Portal sem senha (login
     * mágico) — e a página ganha o caminho de volta: Central › Projeto/Avulsas.
     * Link vencido não dá acesso a nada.
     *
     * @return array{group: ?array{title: string, url: string}}|null
     */
    private function enterCentral(Request $request, TaskApprovalToken $approvalToken): ?array
    {
        // Gente da equipe abrindo o link pra conferir (botão "Link" da Central):
        // abre normal, mas sem logar como o cliente nem contar como aberto.
        if ($approvalToken->isExpired() || Auth::guard('web')->check()) {
            return null;
        }

        $this->magic->enterFromToken($request, $approvalToken);

        $contact = Auth::guard('portal')->user();
        $task    = $approvalToken->round->task;
        if (!$contact || $contact->id !== $approvalToken->contact_id) {
            return null;
        }

        $group = null;
        if ($task->project_id && $task->project && $this->pages->items($task->project)->isNotEmpty()) {
            $group = ['title' => $task->project->title, 'url' => route('portal.approvals.project', $task->project)];
        } elseif (!$task->project_id) {
            $group = ['title' => 'Peças avulsas', 'url' => route('portal.approvals.loose')];
        }

        return ['group' => $group];
    }

    /**
     * Primeira abertura do link pelo contato (atendimento: "nem abriu" vs
     * "abriu e não respondeu"). Ignora a pré-visualização que o WhatsApp e
     * outros apps geram sozinhos ao receber o link, e gente da equipe.
     */
    private function markOpened(Request $request, TaskApprovalToken $approvalToken): void
    {
        if ($approvalToken->first_opened_at || Auth::guard('web')->check()) {
            return;
        }

        $ua = strtolower((string) $request->userAgent());
        foreach (['whatsapp', 'facebookexternalhit', 'facebot', 'bot', 'crawler', 'spider', 'preview', 'telegram', 'slack', 'discord', 'skype'] as $needle) {
            if ($ua === '' || str_contains($ua, $needle)) {
                return;
            }
        }

        $approvalToken->forceFill(['first_opened_at' => now()])->saveQuietly();
    }

    // Link do lembrete único (ApprovalReminderService): entra direto na Central.
    // Sem acesso (link vencido, equipe, contato desligado) → página da peça.
    public function central(Request $request, string $token)
    {
        $approvalToken = TaskApprovalToken::where('token', $token)->with('round.task.project')->firstOrFail();

        if (!$this->enterCentral($request, $approvalToken)) {
            return redirect()->route('approval.show', $token);
        }

        $this->markOpened($request, $approvalToken);

        return redirect()->route('portal.approvals.index');
    }

    public function show(Request $request, string $token)
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
                // Comentários marcados como visíveis pro cliente na tarefa — é a
                // "venda" da arte feita pelo designer, explicando a produção.
                'round.task.comments.user',
                'round.task.comments.contact',
                'contact',
            ])
            ->firstOrFail();

        // Aviso não pede decisão — o token já nasce "approved" (ver
        // TaskApprovalService::sendAviso()), então isValid()/isPending() não
        // se aplicam; só a expiração importa pro link continuar acessível.
        $isAviso = $approvalToken->round->isAviso();
        $deliverables = $approvalToken->round->deliverables();
        $centralNav = $this->enterCentral($request, $approvalToken);
        $this->markOpened($request, $approvalToken);

        if (! $isAviso && ! $approvalToken->isValid()) {
            return view('approval.expired', compact('approvalToken', 'deliverables', 'centralNav'));
        }

        if ($isAviso && $approvalToken->isExpired()) {
            return view('approval.expired', compact('approvalToken', 'deliverables', 'centralNav'));
        }
        $batch = $this->batchForContact($approvalToken);
        $visibleComments = $approvalToken->round->task->comments
            ->where('visible_to_client', true)
            ->sortBy('created_at');

        // "Revisar pendentes" em andamento — barra "peça X de N" no topo.
        $review = $centralNav ? $this->queue->state($request, $approvalToken) : null;

        return view('approval.show', compact('approvalToken', 'deliverables', 'batch', 'visibleComments', 'centralNav', 'review'));
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

        // Dentro do "Revisar pendentes": vai direto pra próxima peça; na última,
        // volta pra Central/Projeto de onde a revisão começou.
        if ($step = $this->queue->advance($request, $approvalToken)) {
            if ($step['next']) {
                return redirect()->route('approval.show', $step['next'])
                    ->with('review_msg', 'Resposta registrada. Esta é a próxima peça.');
            }

            return redirect($step['back'])->with('success', $step['done'] === 1
                ? 'Revisão concluída! Você respondeu 1 peça. Obrigado.'
                : "Revisão concluída! Você respondeu {$step['done']} peças. Obrigado.");
        }

        $approvalToken->refresh();
        $approvalToken->load('round.task');

        // Só existe entregável pra baixar se a rodada (não só este token) já fechou
        // aprovada — com mais de um aprovador, pode faltar gente decidir ainda.
        $deliverables = $approvalToken->round->status === 'approved'
            ? $approvalToken->round->deliverables()
            : collect();

        $centralNav = $this->enterCentral($request, $approvalToken);

        return view('approval.thanks', compact('approvalToken', 'deliverables', 'centralNav'));
    }
}

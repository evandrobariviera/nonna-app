<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\MacroPlan;
use App\Models\User;
use App\Services\Documents\PlanningDrafter;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Rascunho do planejamento pelo Agente Nonna (PlanningDrafter) — leva 1 a 3 minutos. A situação
 * fica em documents.agent_state.draft (processando / pronto / erro); a aba Documento pergunta e
 * recarrega sozinha, e quem pediu recebe aviso no sino (pode ter saído da página).
 */
class DraftPlanningDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly string $documentId,
        public readonly string $planId,
        public readonly array $keys,
        public readonly array $uploadIds,
        public readonly int $userId,
        public readonly string $note = '',
    ) {}

    // retry_after da fila é 90s: sem isso outro worker re-reservaria e rodaria em dobro.
    public function middleware(): array
    {
        return [(new WithoutOverlapping('document-draft:' . $this->documentId))->releaseAfter(60)->expireAfter(900)];
    }

    public function handle(PlanningDrafter $drafter, NotificationService $notifications): void
    {
        $document = Document::withoutGlobalScopes()->find($this->documentId);
        $plan     = MacroPlan::withoutGlobalScopes()->find($this->planId);
        if (!$document || !$plan || (($document->agent_state['draft']['status'] ?? null) !== 'processando')) {
            return;
        }
        app()->instance('currentOrganization', \App\Models\Organization::find($document->organization_id));

        try {
            $result = $drafter->draft($document, $plan, $this->keys, $this->uploadIds, $this->userId, $this->note);
            $this->setState($document, [
                'status' => 'pronto', 'finished_at' => now()->toIso8601String(),
                'used' => $result['used'], 'skipped' => $result['skipped'],
            ], $result['perguntas']);
            $title = 'Rascunho do planejamento pronto';
            $body  = count($result['perguntas']) . ' pergunta(s) do agente esperando resposta. Revise seção por seção.';
        } catch (\Throwable $e) {
            $this->setState($document, ['status' => 'erro', 'finished_at' => now()->toIso8601String(), 'error' => mb_substr($e->getMessage(), 0, 400)]);
            $title = 'Não foi possível montar o rascunho';
            $body  = mb_substr($e->getMessage(), 0, 300);
            if (!$e instanceof \InvalidArgumentException) {
                report($e);
            }
        }

        if ($user = User::find($this->userId)) {
            $notifications->notifyUsers(collect([$user]), 'automation', "{$title}: {$plan->title}", $body,
                route('macroplans.edit', [$plan, 'bloco' => 'documento']), null, $document->organization_id);
        }
    }

    public function failed(?\Throwable $e): void
    {
        $document = Document::withoutGlobalScopes()->find($this->documentId);
        if ($document && ($document->agent_state['draft']['status'] ?? null) === 'processando') {
            $this->setState($document, ['status' => 'erro', 'finished_at' => now()->toIso8601String(),
                'error' => 'O agente passou do tempo e o rascunho foi cancelado. Tente de novo.']);
        }
    }

    private function setState(Document $document, array $draft, ?array $perguntas = null): void
    {
        $state = $document->fresh()->agent_state ?? [];
        $state['draft'] = array_merge($state['draft'] ?? [], $draft);
        if ($perguntas !== null) {
            $state['perguntas'] = $perguntas;
        }
        Document::withoutGlobalScopes()->whereKey($document->id)->update(['agent_state' => json_encode($state, JSON_UNESCAPED_UNICODE)]);
    }
}

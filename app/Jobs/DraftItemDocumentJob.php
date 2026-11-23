<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Services\Documents\DocumentDrafter;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Rascunho da campanha/projeto pelo Agente Nonna (DocumentDrafter), em segundo plano — mesmo
 * jeito do planejamento (DraftPlanningDocumentJob): situação em documents.agent_state.draft e
 * aviso no sino para quem pediu.
 */
class DraftItemDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly string $documentId,
        public readonly string $projectId,
        public readonly array $keys,
        public readonly array $uploadIds,
        public readonly int $userId,
        public readonly string $note = '',
    ) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('document-draft:' . $this->documentId))->releaseAfter(60)->expireAfter(900)];
    }

    public function handle(DocumentDrafter $drafter, NotificationService $notifications): void
    {
        $document = Document::withoutGlobalScopes()->find($this->documentId);
        $project  = Project::withoutGlobalScopes()->find($this->projectId);
        if (!$document || !$project || (($document->agent_state['draft']['status'] ?? null) !== 'processando')) {
            return;
        }
        app()->instance('currentOrganization', \App\Models\Organization::find($document->organization_id));
        $what = $project->type === 'campanha' ? 'da campanha' : 'do projeto';

        try {
            $result = $drafter->draft($document, $project, $this->keys, $this->uploadIds, $this->userId, $this->note);
            $this->setState($document, ['status' => 'pronto', 'finished_at' => now()->toIso8601String(),
                'used' => $result['used'], 'skipped' => $result['skipped']], $result['perguntas']);
            $title = "Rascunho {$what} pronto";
            $body  = count($result['perguntas']) . ' pergunta(s) do agente esperando resposta.';
        } catch (\Throwable $e) {
            $this->setState($document, ['status' => 'erro', 'finished_at' => now()->toIso8601String(), 'error' => mb_substr($e->getMessage(), 0, 400)]);
            $title = "Não foi possível montar o rascunho {$what}";
            $body  = mb_substr($e->getMessage(), 0, 300);
            if (!$e instanceof \InvalidArgumentException) {
                report($e);
            }
        }

        $url = $project->macro_plan_id ? route('macroplans.projects.show', [$project->macro_plan_id, $project]) : route('projects.showDirect', $project);
        if ($user = User::find($this->userId)) {
            $notifications->notifyUsers(collect([$user]), 'automation', "{$title}: {$project->title}", $body, $url, null, $document->organization_id);
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
        $state = Document::withoutGlobalScopes()->find($document->id)->agent_state ?? [];
        $state['draft'] = array_merge($state['draft'] ?? [], $draft);
        if ($perguntas !== null) {
            $state['perguntas'] = $perguntas;
            unset($state['perguntas_ocultas']);
        }
        Document::withoutGlobalScopes()->whereKey($document->id)->update(['agent_state' => json_encode($state, JSON_UNESCAPED_UNICODE)]);
    }
}

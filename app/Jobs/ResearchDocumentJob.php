<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\Project;
use App\Services\Documents\CreativeContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Pesquisa de mercado pedida na coluna do Agente Nonna (24/11). Roda em segundo plano porque leva
 * de 1 a 2 minutos; o resultado fica em documents.agent_state.pesquisa e o agente da coluna usa
 * em toda pergunta seguinte, sem custo extra.
 */
class ResearchDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 400;
    public int $tries = 1;

    public function __construct(public string $documentId, public string $projectId, public int $userId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping("pesquisa-{$this->documentId}"))->dontRelease()];
    }

    public function handle(CreativeContext $creative): void
    {
        $document = Document::withoutGlobalScopes()->find($this->documentId);
        $project  = Project::withoutGlobalScopes()->find($this->projectId);
        if ($document && $project) {
            try {
                $creative->market($document, $project, $this->userId, fresh: true);
            } finally {
                $this->state($document, null); // libera o "pesquisando" mesmo se a pesquisa falhar
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        if ($document = Document::withoutGlobalScopes()->find($this->documentId)) {
            $this->state($document, null);
        }
    }

    private function state(Document $document, ?string $value): void
    {
        $state = Document::withoutGlobalScopes()->find($document->id)->agent_state ?? [];
        $state['pesquisando'] = $value;
        Document::withoutGlobalScopes()->whereKey($document->id)->update(['agent_state' => json_encode($state, JSON_UNESCAPED_UNICODE)]);
    }
}

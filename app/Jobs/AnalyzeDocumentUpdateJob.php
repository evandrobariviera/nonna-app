<?php

namespace App\Jobs;

use App\Models\DocumentUpdateRun;
use App\Models\User;
use App\Services\DocumentUpdate\DocumentUpdateService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

// "Atualizar documento com reunião" (DocumentUpdateService): a IA lê documento + skill +
// ATA + transcrição — leva alguns minutos. A página da análise pergunta o status e recarrega;
// quem pediu também recebe aviso no sino (pode ter saído da página).
class AnalyzeDocumentUpdateJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public readonly string $runId) {}

    // retry_after da fila é 90s: sem isso outro worker re-reservaria e rodaria em dobro.
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('document-update:' . $this->runId))
                ->releaseAfter(60)
                ->expireAfter(900),
        ];
    }

    public function handle(DocumentUpdateService $service, NotificationService $notifications): void
    {
        $run = DocumentUpdateRun::withoutGlobalScopes()->find($this->runId);
        if (!$run || !$run->isProcessing()) {
            return;
        }

        try {
            $service->analyze($run);
            $title = match ($run->strategic_review['veredito'] ?? null) {
                'risco_de_desvio' => 'Reunião com risco de desvio da linha',
                'mudanca_de_rota' => 'Reunião mudou a rota do documento',
                'alinhado'        => 'Reunião na linha do documento',
                default           => 'Leitura da reunião pronta',
            };
            $body = count($run->suggestions ?? []) . ' sugestão(ões) para o documento — confira a leitura estratégica e aplique.';
        } catch (\Throwable $e) {
            $run->update(['status' => 'erro', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            $title = 'Não foi possível analisar o documento';
            $body = mb_substr($e->getMessage(), 0, 300);
            report($e);
        }

        $user = User::find($run->requested_by);
        if ($user) {
            $notifications->notifyUsers(
                collect([$user]),
                'automation',
                $title . ': ' . ($run->owner()?->title ?? ''),
                $body,
                route('document-updates.show', $run),
                null,
                $run->organization_id,
            );
        }
    }
}

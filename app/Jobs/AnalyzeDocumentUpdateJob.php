<?php

namespace App\Jobs;

use App\Models\DocumentUpdateRun;
use App\Models\User;
use App\Services\DocumentUpdate\DocumentUpdateService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\TimeoutExceededException;

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
            // Query nova, não $run->update(): se o erro veio do próprio save do analyze(), o
            // $run ainda carrega os atributos sujos e a gravação do "erro" falharia junto.
            $run = $run->fresh();
            $run->update(['status' => 'erro', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            $title = 'Não foi possível analisar o documento';
            $body = mb_substr($e->getMessage(), 0, 300);
            report($e);
        }

        $this->notify($run, $notifications, $title, $body);
    }

    // Só o estouro do timeout próprio (600s) marca erro aqui. O MaxAttemptsExceeded "puro"
    // aparece quando outro worker re-reserva o job depois do retry_after (90s) — o worker
    // original segue rodando e conclui. Worker morto no meio (deploy) é coberto por
    // DocumentUpdateRun::releaseIfStuck().
    public function failed(?\Throwable $e): void
    {
        if (!$e instanceof TimeoutExceededException) {
            return;
        }

        $run = DocumentUpdateRun::withoutGlobalScopes()->find($this->runId);
        if (!$run || !$run->isProcessing()) {
            return;
        }

        $run->update(['status' => 'erro', 'error' => 'A IA passou de 10 minutos sem responder e a análise foi cancelada. Tente de novo.']);
        $this->notify($run, app(NotificationService::class), 'Não foi possível analisar o documento', $run->error);
    }

    private function notify(DocumentUpdateRun $run, NotificationService $notifications, string $title, string $body): void
    {
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

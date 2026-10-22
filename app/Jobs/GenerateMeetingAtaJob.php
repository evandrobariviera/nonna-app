<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingAtaService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;

// Botão "Gerar ATA com IA" da reunião (ver MeetingAtaService). Enquanto roda, a página
// mostra "Gerando ATA…" (chave no cache, apagada no fim — dê certo ou não).
class GenerateMeetingAtaJob implements ShouldQueue
{
    use Queueable;

    // Agente de IA com transcrição longa passa fácil do --timeout=90 do worker.
    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public readonly string $meetingId,
        public readonly int $requestedBy,
    ) {}

    // retry_after da fila é 90s: sem isso outro worker re-reservaria e geraria em dobro.
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('meeting-ata:' . $this->meetingId))
                ->releaseAfter(60)
                ->expireAfter(600),
        ];
    }

    public function handle(MeetingAtaService $service, NotificationService $notifications): void
    {
        $meeting = Meeting::find($this->meetingId);
        if (!$meeting) {
            Cache::forget(MeetingAtaService::generatingKey($this->meetingId));

            return;
        }

        try {
            $agent = MeetingAtaService::defaultAgent()
                ?? throw new \RuntimeException('Nenhum agente de IA ativo pra ATA (Agentes & IA → "ATA Estruturada").');

            // O aviso aos participantes sai do MeetingObserver (vale pra toda mudança da ATA).
            MeetingAtaService::$actingUserId = $this->requestedBy;
            $service->generate($meeting, $agent, $this->requestedBy, 'meeting:gerar_ata');
        } catch (\Throwable $e) {
            $user = User::find($this->requestedBy);
            if ($user) {
                $notifications->notifyUsers(
                    collect([$user]),
                    'automation',
                    'Não foi possível gerar a ATA: ' . $meeting->title,
                    mb_substr($e->getMessage(), 0, 300),
                    route('meetings.show', $meeting),
                    $meeting,
                    $meeting->organization_id,
                );
            }
            report($e);
        } finally {
            MeetingAtaService::$actingUserId = null;
            Cache::forget(MeetingAtaService::generatingKey($this->meetingId));
        }
    }
}

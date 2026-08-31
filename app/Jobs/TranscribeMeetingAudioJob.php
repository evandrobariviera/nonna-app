<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Models\MeetingAttachment;
use App\Models\User;
use App\Services\AiService;
use App\Services\SystemNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Collection;

class TranscribeMeetingAudioJob implements ShouldQueue
{
    use Queueable;

    // Transcrição de reunião de 2-3h segura o worker por vários minutos. O worker
    // do supervisord roda com --timeout=90; esta propriedade tem precedência e
    // vale só pra este job.
    public int $timeout = 1200;

    public int $tries = 2;

    public function __construct(
        public readonly string $meetingId,
        public readonly string $attachmentId,
        public readonly int $requestedBy,
    ) {}

    /**
     * retry_after da fila (database) é 90s — bem menor que a duração deste job, então
     * sem isso o outro worker re-reservaria e rodaria a transcrição em paralelo
     * (custo e notificação em dobro). WithoutOverlapping serializa por reunião.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('meeting-transcription:' . $this->meetingId))
                ->releaseAfter(180)
                ->expireAfter(1800),
        ];
    }

    public function handle(AiService $aiService, SystemNotificationService $notifications): void
    {
        $meeting = Meeting::find($this->meetingId);
        $attachment = MeetingAttachment::find($this->attachmentId);
        $user = User::find($this->requestedBy);

        if (!$meeting || !$attachment || !$user) {
            return;
        }

        $transcript = $aiService->transcribeAudioLong($attachment->url());

        if ($transcript) {
            // Substitui a transcrição anterior, mesmo comportamento do campo manual
            // (ver meetings/edit.blade.php) — a automação structure_ata sempre lê a
            // transcrição mais recente. transcricao_updated_at alimenta o
            // finalize_macro_meeting (decide se re-processa a ATA).
            $meeting->update([
                'transcricao'            => $transcript,
                'transcricao_updated_at' => now(),
            ]);
        }

        $notifications->send(
            $transcript ? 'meeting.transcricao_ok' : 'meeting.transcricao_falhou',
            Collection::make([$user]),
            ['attachment_name' => $attachment->filename, 'meeting_title' => $meeting->title],
            route('meetings.show', $meeting),
            $meeting,
            $meeting->organization_id
        );
    }
}

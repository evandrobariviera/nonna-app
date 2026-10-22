<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\InternalNotification;
use App\Models\Meeting;
use App\Models\Pendency;
use Illuminate\Support\Facades\Cache;

/**
 * ATA da reunião gerada pela IA a partir da Transcrição (2026-10-22): antes rodava sozinha
 * quando a reunião entrava em Revisão Interna (automação structure_ata); agora é o botão
 * "Gerar ATA com IA" na reunião (GenerateMeetingAtaJob). Mesma estrutura de sempre — o
 * agente "ATA Estruturada" — e, se a ATA já existe, o agente acrescenta o que é novo sem
 * reescrever do zero.
 *
 * Pronta, avisa os participantes com o "✓ Li a ATA" (InternalNotification::KIND_ATA) — de
 * novo a cada atualização ("ATA atualizada"). A pendência "Registrar a ATA" do organizador
 * se resolve sozinha quando a ATA é preenchida (MeetingObserver).
 */
class MeetingAtaService
{
    // Marcador entre ATA e "pauta de revisão interna" que o agente ainda pode devolver; a
    // pauta da IA não é gravada (a pauta é do time) — só é cortada da ATA.
    private const PAUTA_MARKER = '===PAUTA_REVISAO_INTERNA===';

    public function __construct(
        private AiService $ai,
        private NotificationService $notifications,
    ) {}

    /** Agente padrão do botão (slug criado por migration; editável em Agentes & IA). */
    public static function defaultAgent(): ?AiAgent
    {
        return AiAgent::where('slug', AiAgent::SLUG_ATA)->where('is_active', true)->first();
    }

    public static function generatingKey(string $meetingId): string
    {
        return "meeting-ata-generating:{$meetingId}";
    }

    public static function isGenerating(Meeting $meeting): bool
    {
        return Cache::has(self::generatingKey($meeting->id));
    }

    /**
     * Gera (ou atualiza) a ATA e grava. Devolve true se era uma ATA nova, false se
     * atualizou uma que já existia.
     */
    public function generate(Meeting $meeting, AiAgent $agent, ?int $userId, string $trigger): bool
    {
        if (blank($meeting->transcricao)) {
            throw new \RuntimeException('Reunião sem Transcrição preenchida — não é possível gerar a ATA.');
        }

        $meeting->loadMissing('client');
        $isNew = blank($meeting->ata);

        $userMessage = "Tipo da reunião: {$meeting->typeLabel()}\n"
            . 'Cliente: ' . ($meeting->client?->displayName() ?? '—') . "\n"
            . "Data da reunião: {$meeting->scheduled_at->format('d/m/Y')}\n\n";

        if (!$isNew) {
            $userMessage .= "ATA JÁ EXISTENTE (atualize e enriqueça, não substitua do zero):\n" . MeetingText::toMarkdown($meeting->ata) . "\n\n";
        }

        $userMessage .= "TRANSCRIÇÃO BRUTA (nova):\n{$meeting->transcricao}\n\n"
            . 'Gere a ATA seguindo o formato definido.';

        $response = $this->ai->run(
            agent: $agent,
            userMessage: $userMessage,
            userId: $userId,
            clientId: $meeting->client_id,
            trigger: $trigger,
        );

        $meeting->update([
            'ata'             => MeetingText::fromAi(self::stripAgenda($response)),
            'ata_recorded_at' => now(),
        ]);

        return $isNew;
    }

    // Quem disparou a geração no job (fila não tem usuário logado) — o MeetingObserver
    // usa pra não avisar a própria pessoa.
    public static ?int $actingUserId = null;

    /**
     * Avisa os participantes (menos quem mudou a ATA) — é o aviso com "✓ Li a ATA". Chamado
     * pelo MeetingObserver a CADA mudança da ATA (IA ou à mão; decisão do Evandro,
     * 2026-10-22). Quem ainda tem um aviso dessa ATA sem confirmar não recebe outro: vai
     * ler a versão mais nova de qualquer jeito — evita encher o sino de quem salva aos poucos.
     */
    public function notifyParticipants(Meeting $meeting, bool $isNew, ?int $actorId): void
    {
        $pending = InternalNotification::withoutGlobalScopes()
            ->where('source_type', 'Meeting')
            ->where('source_id', $meeting->id)
            ->where('kind', InternalNotification::KIND_ATA)
            ->where('status', '!=', 'resolvido')
            ->pluck('user_id');

        $recipients = $meeting->participants()
            ->when($actorId, fn ($q) => $q->where('users.id', '!=', $actorId))
            ->whereNotIn('users.id', $pending)
            ->get();
        if ($recipients->isEmpty()) {
            return;
        }

        $this->notifications->notifyUsers(
            $recipients,
            InternalNotification::KIND_ATA,
            ($isNew ? 'ATA pronta: ' : 'ATA atualizada: ') . $meeting->title,
            $isNew ? 'A ATA da reunião está pronta — leia e confirme.' : 'A ATA mudou desde a sua última leitura — leia de novo e confirme.',
            route('meetings.show', $meeting),
            $meeting,
            $meeting->organization_id,
        );
    }

    /** ATA preenchida (pela IA ou à mão) → resolve a pendência "Registrar a ATA". */
    public static function resolveAtaPendencies(Meeting $meeting, ?int $userId): void
    {
        $pendencies = Pendency::where('pendable_type', 'meeting')
            ->where('pendable_id', $meeting->id)
            ->where('title', 'like', Meeting::ATA_PENDENCY_PREFIX . '%')
            ->open()
            ->get();

        foreach ($pendencies as $pendency) {
            app(PendencyService::class)->resolve($pendency, $userId ?? (int) $meeting->organized_by, 'ATA registrada.');
        }
    }

    private static function stripAgenda(string $response): string
    {
        $pos = mb_strpos($response, self::PAUTA_MARKER);

        return trim($pos === false ? $response : mb_substr($response, 0, $pos));
    }
}

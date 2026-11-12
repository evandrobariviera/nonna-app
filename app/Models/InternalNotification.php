<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalNotification extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'user_id',
        'kind',
        'title',
        'body',
        'link',
        'source_type',
        'source_id',
        'status',
        'generated_at',
        'repeat_count', // avisos iguais não abertos juntados num só (NotificationService)
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'repeat_count' => 'integer',
    ];

    public static array $statuses = [
        'novo'       => ['label' => 'Novo',       'color' => 'purple'],
        'lido'       => ['label' => 'Lido',       'color' => 'blue'],
        'resolvido'  => ['label' => 'Resolvido',  'color' => 'green'],
        'descartado' => ['label' => 'Descartado', 'color' => 'muted'],
        // Perdeu o sentido sozinho (reunião passou, tarefa fechou, informativo velho) — ver
        // TidyNotifications. Ninguém precisou clicar; não conta como "resolvido".
        'expirado'   => ['label' => 'Expirou',    'color' => 'muted'],
    ];

    // ── Atenção com qualidade (2026-11-12) ────────────────────────────────────────────
    // Levantamento: metade do sino era aviso velho e todo aviso pedia uma decisão (3 botões).

    /** "Pendência resolvida" — só informa quem pediu; antes saía com o kind 'pendencia'. */
    public const KIND_PENDENCY_RESOLVED = 'pendencia_resolvida';

    /**
     * Fila do setor, não aviso pessoal: cada cópia vai pra todo o papel e qualquer um fecha
     * pra todos. Mora na própria fila (Mídia Paga, na Dashboard) e fica fora do sino, do
     * popup, do card de avisos e do Seu foco.
     */
    public const QUEUE_KINDS = ['criativo_pronto_campanha'];

    /** Conversa — some do sino quando a pessoa abre; expira em 14 dias ou com o item encerrado. */
    public const CONVERSATION_KINDS = ['project_comment', 'macro_plan_comment'];

    /** Só informação — some quando abre; expira sozinho em 3 dias. */
    public const INFO_KINDS = [
        self::KIND_PENDENCY_RESOLVED, 'oportunidade_ganha', 'oportunidade_perdida',
        'portal_senha_definida', 'cadastro_atualizado',
    ];

    /** Lembrete de reunião — some quando abre; expira quando a reunião começa. */
    public const AGENDA_KINDS = ['reuniao_lembrete'];

    /** Abrir o aviso já conta como lido (não pede "Resolver"). Os outros pedem uma ação. */
    public function isSeenOnOpen(): bool
    {
        return in_array($this->kind, [...self::CONVERSATION_KINDS, ...self::INFO_KINDS, ...self::AGENDA_KINDS], true);
    }

    /** O que aparece no sino/popup/card de avisos (filas de setor ficam de fora). */
    public function scopeInBell($query)
    {
        return $query->where(fn ($q) => $q->whereNull('kind')->orWhereNotIn('kind', self::QUEUE_KINDS));
    }

    // 'kind' é texto livre (preenchido na automação que gera a notificação — ver
    // AutomationJob::sendNotification) — mapa best-effort só pros valores conhecidos hoje;
    // kind sem entrada aqui cai no ícone genérico (mesmo padrão de Task::$typeIcons).
    public static array $kindIcons = [
        'criativo_pronto_campanha' => 'palette',
        'criativo_pronto_dev'      => 'palette',
        'oportunidade_ganha'       => 'party-popper',
        'oportunidade_perdida'     => 'ban',
        'contrato_analise'         => 'file-text',
        'reuniao_lembrete'         => 'calendar',
        'saldo_baixo'              => 'credit-card',
        'automation'               => 'zap',
        'modulo_solicitado'        => 'megaphone',
        'meeting_transcribed'      => 'mic',
        'meeting_transcribe_failed' => 'mic',
        'pendencia'                => 'hourglass',
        self::KIND_PENDENCY_RESOLVED => 'circle-check',
        'project_comment'          => 'message-circle',
        'macro_plan_comment'       => 'messages-square',
        'ajuste_cliente'           => 'rotate-ccw',
        'ia_conta'                 => 'triangle-alert', // crédito/chave da IA (AiService::guard)
        self::KIND_ATA             => 'book-open',
    ];

    // "Leia a ATA" — identificador posto na automação que avisa os participantes quando a
    // reunião entra em Revisão Interna. Resolver = "Li a ATA" (é a confirmação de leitura
    // que a página da reunião mostra) e não pode ser descartada: sumir com o aviso sem
    // confirmar escondia quem chega sem ler na Revisão Interna.
    public const KIND_ATA = 'ata_leitura';

    public function isAtaReading(): bool
    {
        return $this->kind === self::KIND_ATA;
    }

    public function canDiscard(): bool
    {
        return ! $this->isAtaReading();
    }

    /**
     * Dica do ✓ — no aviso de pendência, resolver o AVISO não resolve a pendência (isso se
     * faz na própria pendência). Todo aviso tem ✓ / lido-não lido / apagar: a pessoa
     * controla o próprio sino (pedido do Evandro, 2026-10-20).
     */
    public function resolveHint(): string
    {
        return $this->kind === 'pendencia'
            ? 'Dar este aviso como resolvido — a pendência continua aberta até ser resolvida nela'
            : 'Marcar como resolvido';
    }

    public function resolveLabel(): string
    {
        return $this->isAtaReading() ? 'Li a ATA' : 'Resolver';
    }

    public function statusLabel(): string
    {
        if ($this->isAtaReading() && $this->status === 'resolvido') {
            return 'Leu a ATA';
        }

        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function kindIcon(): string
    {
        return self::$kindIcons[$this->kind] ?? 'bell';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

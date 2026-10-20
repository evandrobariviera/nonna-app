<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Pendência — algo que alguém precisa entregar pra o trabalho andar (ver migration
 * create_pendencies_table). Diferente da Trava (tasks.blocker_at): Trava = "esta tarefa
 * segura as outras"; Pendência = "esta tarefa espera algo". Não se ligam sozinhas.
 */
class Pendency extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'client_id', 'pendable_type', 'pendable_id', 'source_comment_id',
        'title', 'description', 'kind',
        'owed_by', 'owed_contact_id', 'owed_user_id', 'owed_party',
        'owner_id', 'requested_by', 'due_date', 'status', 'nudge_count', 'last_nudged_at',
        'resolved_at', 'resolved_by', 'resolution_note', 'created_by',
    ];

    protected $casts = [
        'due_date'       => 'date',
        'last_nudged_at' => 'datetime',
        'resolved_at'    => 'datetime',
    ];

    public static array $kinds = [
        'insumo'      => ['label' => 'Insumo / material',   'icon' => 'package'],
        'informacao'  => ['label' => 'Informação / dado',   'icon' => 'file-text'],
        'acesso'      => ['label' => 'Acesso',              'icon' => 'key-round'],
        'agendamento' => ['label' => 'Agendamento',         'icon' => 'calendar'],
        'decisao'     => ['label' => 'Decisão / aprovação', 'icon' => 'circle-check'],
        'pagamento'   => ['label' => 'Pagamento',           'icon' => 'circle-dollar-sign'],
        'outro'       => ['label' => 'Outro',               'icon' => 'list-checks'],
    ];

    public static array $owedBy = [
        'cliente'  => ['label' => 'Cliente',  'column' => 'Aguardando cliente',   'color' => 'orange'],
        'agencia'  => ['label' => 'Agência',  'column' => 'Aguardando nós',       'color' => 'purple'],
        'terceiro' => ['label' => 'Terceiro', 'column' => 'Aguardando terceiros', 'color' => 'blue'],
    ];

    public static array $statuses = [
        'aberta'    => ['label' => 'Aberta',    'color' => 'muted'],
        'cobrada'   => ['label' => 'Cobrada',   'color' => 'orange'],
        'resolvida' => ['label' => 'Resolvida', 'color' => 'green'],
        'cancelada' => ['label' => 'Cancelada', 'color' => 'red'],
    ];

    public const OPEN_STATUSES = ['aberta', 'cobrada'];

    // kind das notificações internas geradas por pendência (ver PendencyService)
    public const NOTIFICATION_KIND = 'pendencia';

    // Aliases do morph (ver AppServiceProvider::boot) — o form manda o alias, nunca a classe.
    public const PENDABLE_TYPES = [
        'task'       => Task::class,
        'project'    => Project::class,
        'macro_plan' => MacroPlan::class,
    ];

    public function pendable(): MorphTo
    {
        return $this->morphTo();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'owed_contact_id');
    }

    public function owedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owed_user_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Quem precisa da entrega (ex.: Marlon pede, cliente deve, Alisson cobra). */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function nudges(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PendencyNudge::class)->orderByDesc('created_at');
    }

    /** Botão de cobrança: só quem cobra (ou admin, cobrindo alguém). */
    public function canChase(?int $userId, bool $isAdmin = false): bool
    {
        return $this->isOpen() && ($isAdmin || ($userId && (int) $this->owner_id === $userId));
    }

    /** "Lembrar quem cobra": pra quem precisa (ou admin) apressar quem cobra. */
    public function canRemindOwner(?int $userId, bool $isAdmin = false): bool
    {
        return $this->isOpen() && $this->owner_id && (int) $this->owner_id !== (int) $userId
            && ($isAdmin || ($userId && (int) $this->requested_by === $userId));
    }

    /**
     * Link do WhatsApp do contato que deve, com a mensagem de cobrança pronta — null se
     * não for "deve: cliente" com contato que tenha WhatsApp/telefone.
     */
    public function whatsappUrl(string $senderName): ?string
    {
        if ($this->owed_by !== 'cliente' || !$this->owedContact) {
            return null;
        }
        $digits = preg_replace('/\D/', '', (string) ($this->owedContact->whatsapp ?: $this->owedContact->phone));
        if (strlen($digits) < 10) {
            return null;
        }
        if (strlen($digits) <= 11) {
            $digits = '55' . $digits; // número salvo sem DDI
        }

        $first = \Illuminate\Support\Str::of($this->owedContact->name)->explode(' ')->first();
        $me    = \Illuminate\Support\Str::of($senderName)->explode(' ')->first();
        $due   = $this->due_date ? ' (prazo ' . $this->due_date->format('d/m') . ')' : '';
        $text  = "Oi {$first}, tudo bem? Aqui é {$me}, da Nonna. Ainda estamos precisando de: {$this->title}{$due}. Consegue nos enviar?";

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceComment(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'source_comment_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    /** "Minhas": eu cobro OU eu devo entregar (deve: agência, pessoa = eu). */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(fn ($q) => $q->where('owner_id', $userId)
            ->orWhere(fn ($o) => $o->where('owed_by', 'agencia')->where('owed_user_id', $userId)));
    }

    /**
     * Quantas pendências abertas a tarefa tem — pra ampulheta nos cartões (Fila, Sprint,
     * Projeto). UMA consulta por request (once) com as contagens de todas as tarefas: as
     * abertas são poucas, então isso sai mais barato que withCount em cada listagem.
     */
    public static function openCountForTask(string $taskId): int
    {
        $counts = once(fn () => static::open()
            ->where('pendable_type', 'task')
            ->groupBy('pendable_id')
            ->selectRaw('pendable_id, count(*) as total')
            ->pluck('total', 'pendable_id'));

        return (int) ($counts[$taskId] ?? 0);
    }

    /** Sou quem deve entregar (vs. quem cobra)? */
    public function isOwedBy(int $userId): bool
    {
        return $this->owed_by === 'agencia' && (int) $this->owed_user_id === $userId;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function kindLabel(): string
    {
        return self::$kinds[$this->kind]['label'] ?? $this->kind;
    }

    public function kindIcon(): string
    {
        return self::$kinds[$this->kind]['icon'] ?? 'list-checks';
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    /** Nome de quem deve entregar — contato, pessoa do time ou terceiro. */
    public function owedName(): string
    {
        return match ($this->owed_by) {
            'cliente'  => $this->owedContact?->name ?? 'Cliente',
            'agencia'  => $this->owedUser?->name ?? 'Agência',
            'terceiro' => $this->owed_party ?: 'Terceiro',
            default    => '—',
        };
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date && $this->due_date->lt(today());
    }

    /**
     * Cor pelo tempo parada — vencida é sempre vermelho; sem prazo, conta dias
     * desde a criação (até 3 dias verde, até 7 laranja, depois vermelho).
     */
    public function agingColor(): string
    {
        if (!$this->isOpen()) {
            return 'var(--muted)';
        }
        if ($this->isOverdue()) {
            return 'var(--red)';
        }
        if ($this->due_date) {
            return $this->due_date->diffInDays(today(), false) >= -1 ? 'var(--orange)' : 'var(--green)';
        }
        $days = $this->daysOpen();

        return $days <= 3 ? 'var(--green)' : ($days <= 7 ? 'var(--orange)' : 'var(--red)');
    }

    /** "venceu há 2 dias" / "vence hoje" / "vence 24/10" / "aberta há 5 dias". */
    public function whenText(): string
    {
        if ($this->due_date) {
            $diff = (int) today()->diffInDays($this->due_date, false);

            return $diff < 0 ? 'venceu há ' . abs($diff) . ' dia' . (abs($diff) > 1 ? 's' : '')
                : ($diff === 0 ? 'vence hoje' : 'vence ' . $this->due_date->format('d/m'));
        }
        $days = $this->daysOpen();

        return $days === 0 ? 'aberta hoje' : 'aberta há ' . $days . ' dia' . ($days > 1 ? 's' : '');
    }

    public function daysOpen(): int
    {
        return (int) $this->created_at->copy()->startOfDay()->diffInDays(today());
    }

    /** Link e rótulo da origem (Tarefa/Ticket, Projeto, Planejamento) — null se avulsa. */
    public function originLink(): ?array
    {
        $p = $this->pendable;

        return match (true) {
            $p instanceof Task      => ['label' => ($p->is_ticket ? 'Ticket' : 'Tarefa') . ': ' . $p->title, 'url' => route('tasks.show', $p)],
            $p instanceof Project   => ['label' => 'Projeto: ' . $p->title, 'url' => route('projects.showDirect', $p)],
            $p instanceof MacroPlan => ['label' => 'Planejamento: ' . $p->title, 'url' => route('macroplans.edit', $p)],
            default                 => null,
        };
    }
}

<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'title', 'starts_at', 'ends_at', 'status',
        'locked_at', 'locked_by', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at'   => 'date',
        'locked_at' => 'datetime',
    ];

    public static array $statuses = [
        'planning' => ['label' => 'Planejamento', 'color' => 'muted'],
        'active'   => ['label' => 'Ativa',        'color' => 'orange'],
        'closed'   => ['label' => 'Encerrada',    'color' => 'green'],
    ];

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    // Toda sprint da Nonna é uma quinzena: 14 dias, começando na segunda, emendando
    // na anterior (confirmado nas 15 primeiras sprints, sem exceção). Não é
    // configurável de propósito — se um dia precisar de período diferente, as datas
    // continuam editáveis no formulário; só a sugestão segue o padrão.
    public const DURATION_DAYS = 14;

    /**
     * Período sugerido pra próxima sprint: emenda na última criada (não na ativa —
     * a equipe cria sprints futuras pra agendar tarefa, então "a próxima" é sempre
     * depois da última do calendário). Sem nenhuma sprint, cai na próxima segunda.
     */
    public static function suggestNextPeriod(): array
    {
        $last = static::orderByDesc('ends_at')->first();

        $start = $last?->ends_at
            ? $last->ends_at->copy()->addDay()
            : now()->startOfWeek();

        return [
            'starts_at' => $start,
            'ends_at'   => $start->copy()->addDays(self::DURATION_DAYS - 1),
        ];
    }

    /**
     * Próximo número da sequência. Lê do próprio título (não existe coluna) — é
     * confiável porque o título passou a ser sempre gerado, nunca digitado.
     */
    public static function nextNumber(): int
    {
        $maior = static::pluck('title')
            ->map(fn ($title) => preg_match('/Sprint\s+(\d+)/i', (string) $title, $m) ? (int) $m[1] : 0)
            ->max();

        return ((int) $maior) + 1;
    }

    /**
     * Título padronizado: "Sprint 16 (19/10/26 - 01/11/26)". Sempre com zero à
     * esquerda — os títulos antigos foram digitados à mão e ficaram inconsistentes
     * (23/3/26 num, 06/9/26 noutro).
     */
    public static function buildTitle(int $number, $startsAt, $endsAt): string
    {
        $fmt = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m/y');

        return sprintf('Sprint %d (%s - %s)', $number, $fmt($startsAt), $fmt($endsAt));
    }

    // Número desta sprint, extraído do título — usado pra manter o número estável
    // quando o título é regerado por mudança de data.
    public function number(): ?int
    {
        return preg_match('/Sprint\s+(\d+)/i', (string) $this->title, $m) ? (int) $m[1] : null;
    }

    public function isLocked(): bool
    {
        return !is_null($this->locked_at);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function activeSprint(): ?self
    {
        return self::where('status', 'active')->latest('starts_at')->first();
    }
}

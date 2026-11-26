<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rodada de IA (etapa 4 da Central de Comércio): perguntas de cliente feitas ao ChatGPT com
 * busca na web, repetidas N vezes. Ver App\Services\Commerce\AiRoundRunner.
 */
class CommerceAiRound extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = ['organization_id', 'city_id', 'segment_id', 'questions', 'repetitions', 'provider', 'status', 'error', 'created_by', 'finished_at'];

    protected $casts = [
        'questions'   => 'array',
        'repetitions' => 'integer',
        'finished_at' => 'datetime',
    ];

    public static array $statuses = [
        'queued'  => ['label' => 'Na fila',       'color' => 'muted'],
        'running' => ['label' => 'Perguntando…',  'color' => 'orange'],
        'done'    => ['label' => 'Concluída',     'color' => 'green'],
        'error'   => ['label' => 'Deu erro',      'color' => 'red'],
    ];

    /** Trigger do registro de uso da IA (ai_token_usage): o custo da rodada sai daí. */
    public function trigger(): string
    {
        return 'prospeccao:rodada_ia:' . $this->id;
    }

    public function expected(): int
    {
        return count($this->questions ?? []) * $this->repetitions;
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(CommerceCity::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(CommerceSegment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CommerceAiAnswer::class, 'round_id');
    }

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(CommercePlace::class, 'commerce_ai_round_place', 'round_id', 'place_id')->withPivot('mentions');
    }

    public function label(): string
    {
        return ($this->segment?->name ?? 'Rodada') . ' · ' . ($this->city?->name ?? '');
    }
}

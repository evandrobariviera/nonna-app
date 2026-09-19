<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma entrada da linha do tempo de Novidades do App — ver a migration
 * create_app_updates_table pra diferença entre isto, notificação e Central de Ajuda.
 */
class AppUpdate extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'title', 'summary', 'body', 'kind', 'area',
        'published_at', 'created_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    // Rótulo e cor por tipo. "Novidade" é coisa que não existia; "melhoria" mexe em algo
    // que já existia; "correção" conserta o que estava errado.
    public static array $kinds = [
        'novidade'  => ['label' => 'Novidade', 'color' => 'var(--purple)'],
        'melhoria'  => ['label' => 'Melhoria', 'color' => 'var(--orange)'],
        'correcao'  => ['label' => 'Correção', 'color' => 'var(--green)'],
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** O que a equipe vê: publicado e com data já passada. */
    public function scopePublicadas(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function kindLabel(): string
    {
        return static::$kinds[$this->kind]['label'] ?? $this->kind;
    }

    public function kindColor(): string
    {
        return static::$kinds[$this->kind]['color'] ?? 'var(--muted)';
    }

    /** Quantas novidades essa pessoa ainda não viu — alimenta o ponto na barra lateral. */
    public static function naoVistasPor(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return static::publicadas()
            ->when($user->app_updates_seen_at, fn ($q) => $q->where('published_at', '>', $user->app_updates_seen_at))
            ->count();
    }
}

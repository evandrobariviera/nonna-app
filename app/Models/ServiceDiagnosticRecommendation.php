<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceDiagnosticRecommendation extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'diagnostic_id',
        'category',
        'title',
        'description',
        'status',
        'resolved_at',
        'position',
        'previous_recommendation_id',
    ];

    protected $casts = [
        'position'    => 'integer',
        'resolved_at' => 'datetime',
    ];

    public static array $categories = [
        'quick_win'  => 'Quick win',
        'estrutural' => 'Estrutural',
    ];

    public static array $statuses = [
        'pendente'     => ['label' => 'Pendente',     'color' => 'muted'],
        'implementada' => ['label' => 'Implementada', 'color' => 'green'],
        'rejeitada'    => ['label' => 'Rejeitada',    'color' => 'red'],
    ];

    public function statusLabel(): string
    {
        return data_get(self::$statuses, "{$this->status}.label", $this->status);
    }

    public function statusColor(): string
    {
        return data_get(self::$statuses, "{$this->status}.color", 'muted');
    }

    // ── Relacionamentos ──

    public function diagnostic(): BelongsTo
    {
        return $this->belongsTo(ServiceDiagnostic::class, 'diagnostic_id');
    }

    // A mesma recomendação na versão anterior (loop causa-efeito)
    public function previousRecommendation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_recommendation_id');
    }

    // A mesma recomendação retomada na versão seguinte, se existir
    public function nextRecommendation(): HasOne
    {
        return $this->hasOne(self::class, 'previous_recommendation_id');
    }

    // Quantas versões seguidas essa recomendação já apareceu (incluindo esta)
    public function streakLength(): int
    {
        $count = 1;
        $current = $this;

        while ($current->previousRecommendation) {
            $current = $current->previousRecommendation;
            $count++;
        }

        return $count;
    }
}

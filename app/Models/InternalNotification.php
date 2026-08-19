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
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public static array $statuses = [
        'novo'       => ['label' => 'Novo',       'color' => 'purple'],
        'lido'       => ['label' => 'Lido',       'color' => 'blue'],
        'resolvido'  => ['label' => 'Resolvido',  'color' => 'green'],
        'descartado' => ['label' => 'Descartado', 'color' => 'muted'],
    ];

    // 'kind' é texto livre (preenchido na automação que gera a notificação — ver
    // AutomationJob::sendNotification) — mapa best-effort só pros valores conhecidos hoje;
    // kind sem entrada aqui cai no ícone genérico (mesmo padrão de Task::$typeIcons).
    public static array $kindIcons = [
        'criativo_pronto_campanha' => 'palette',
        'reuniao_lembrete'         => 'calendar',
        'saldo_baixo'              => 'credit-card',
        'automation'               => 'zap',
        'modulo_solicitado'        => 'megaphone',
    ];

    public function statusLabel(): string
    {
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientModule extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'module_key',
        'status',
        'enabled_at',
        'enabled_by',
        'requested_at',
    ];

    protected $casts = [
        'enabled_at'   => 'datetime',
        'requested_at' => 'datetime',
    ];

    // Reaproveitável pra outros módulos vendáveis futuros — hoje só Central de Leads.
    public static array $modules = [
        'central_leads' => 'Central de Leads',
    ];

    public static array $statuses = [
        'nao_contratado' => ['label' => 'Não Contratado', 'color' => 'muted'],
        'ativo'          => ['label' => 'Ativo',          'color' => 'green'],
    ];

    public function moduleLabel(): string
    {
        return self::$modules[$this->module_key] ?? $this->module_key;
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'ativo';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }
}

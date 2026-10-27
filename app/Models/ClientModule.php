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
        'settings',
    ];

    protected $casts = [
        'settings'     => 'array',
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

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    // Central de Leads: "lead atribuído some para os outros" — com a opção ligada,
    // cada pessoa do cliente no Portal vê só os seus leads e os sem responsável;
    // o contato principal e o time da Nonna continuam vendo todos.
    public static function leadsHideAssigned(string $clientId): bool
    {
        return (bool) self::where('client_id', $clientId)->where('module_key', 'central_leads')
            ->first()?->setting('hide_assigned', false);
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

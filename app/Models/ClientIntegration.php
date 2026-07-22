<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class ClientIntegration extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    // Nunca serializar credenciais para views/JSON - só acessíveis via credential() no servidor
    protected $hidden = ['credentials'];

    protected $fillable = [
        'client_id',
        'provider',
        'label',
        'external_id',
        'credentials',
        'settings',
        'status',
        'last_synced_at',
        'last_verified_at',
    ];

    protected $casts = [
        'settings'         => 'array',
        'last_synced_at'   => 'datetime',
        'last_verified_at' => 'datetime',
    ];

    public static array $providers = [
        'uazapi'      => ['label' => 'WhatsApp (uazapi)', 'category' => 'atendimento'],
        'partner_crm' => ['label' => 'CRM Parceiro',       'category' => 'atendimento'],
    ];

    public static array $statuses = [
        'pending'      => ['label' => 'Aguardando',   'color' => 'muted'],
        'connected'    => ['label' => 'Conectado',    'color' => 'green'],
        'disconnected' => ['label' => 'Desconectado', 'color' => 'orange'],
        'error'        => ['label' => 'Erro',         'color' => 'red'],
    ];

    // ── Credentials criptografadas ──

    public function setCredentialsAttribute(?array $value): void
    {
        $this->attributes['credentials'] = $value ? Crypt::encryptString(json_encode($value)) : null;
    }

    public function getCredentialsAttribute(?string $value): ?array
    {
        if (!$value) return null;
        return json_decode(Crypt::decryptString($value), true);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    // ── Relacionamentos ──

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ServiceConversation::class);
    }

    public function diagnostics(): HasMany
    {
        return $this->hasMany(ServiceDiagnostic::class);
    }

    // ── Helpers ──

    public function providerLabel(): string
    {
        return data_get(self::$providers, "{$this->provider}.label", $this->provider);
    }

    public function statusLabel(): string
    {
        return data_get(self::$statuses, "{$this->status}.label", $this->status);
    }

    public function hasCredentials(): bool
    {
        return !empty($this->attributes['credentials'] ?? null);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function diagnosticFrequencyDays(): int
    {
        return (int) data_get($this->settings, 'diagnostic_frequency_days', 30);
    }

    // Ticket médio configurado manualmente - base pra estimar R$ perdido nos gaps do diagnóstico
    public function avgTicketValue(): ?float
    {
        $value = data_get($this->settings, 'avg_ticket_value');
        return $value !== null ? (float) $value : null;
    }

    // Servidor uazapi da instância (ex: https://nonna.uazapi.com) - necessário pra baixar mídia
    public function baseUrl(): ?string
    {
        return data_get($this->settings, 'base_url');
    }
}

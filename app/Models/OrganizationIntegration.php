<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class OrganizationIntegration extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'provider',
        'label',
        'external_id',
        'credentials',
        'settings',
        'status',
        'last_verified_at',
        'expires_at',
    ];

    protected $casts = [
        'settings'         => 'array',
        'last_verified_at' => 'datetime',
        'expires_at'       => 'datetime',
    ];

    // Providers disponíveis e seus rótulos de exibição
    public static array $providers = [
        // Mídia paga
        'meta'      => ['label' => 'Meta (Facebook/Instagram)', 'category' => 'ads'],
        'google'    => ['label' => 'Google Ads',                'category' => 'ads'],
        'tiktok'    => ['label' => 'TikTok Ads',                'category' => 'ads'],
        'linkedin'  => ['label' => 'LinkedIn Ads',              'category' => 'ads'],
        'pinterest' => ['label' => 'Pinterest Ads',             'category' => 'ads'],
        // Automação
        'clickup'   => ['label' => 'ClickUp',                   'category' => 'automation'],
        'n8n'       => ['label' => 'n8n',                       'category' => 'automation'],
        // Comunicação
        'whatsapp'  => ['label' => 'WhatsApp Cloud API',        'category' => 'communication'],
        'slack'     => ['label' => 'Slack',                     'category' => 'communication'],
        // Analytics
        'ga4'       => ['label' => 'Google Analytics 4',        'category' => 'analytics'],
        'looker'    => ['label' => 'Looker Studio',             'category' => 'analytics'],
    ];

    public static array $statuses = [
        'pending'      => ['label' => 'Aguardando',    'color' => 'muted'],
        'connected'    => ['label' => 'Conectado',     'color' => 'green'],
        'disconnected' => ['label' => 'Desconectado',  'color' => 'orange'],
        'error'        => ['label' => 'Erro',          'color' => 'red'],
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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
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

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}

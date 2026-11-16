<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Rastreamento de um cliente: Pixel da Meta, GA4, conversão do Google Ads e
 * Clarity. Substitui o GTM (web + server). Segredos ficam criptografados.
 */
class ClientTracking extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'client_id', 'meta_pixel_id', 'meta_test_event_code', 'ga4_measurement_id', 'ga4_api_secret',
        'google_ads_account_id', 'google_lead_action', 'google_purchase_action', 'clarity_id', 'site_key', 'site_secret', 'site_secret_rotated_at', 'send_enabled',
        'ga4_property_id', 'search_console_site', 'whatsapp_waba_id',
    ];

    protected $casts = [
        'ga4_api_secret'         => 'encrypted',
        'site_secret'            => 'encrypted',
        'site_secret_rotated_at' => 'datetime',
        'send_enabled'           => 'boolean',
    ];

    protected $hidden = ['ga4_api_secret', 'site_secret'];

    protected static function booted(): void
    {
        static::creating(fn (self $t) => $t->site_key ??= Str::lower(Str::random(24)));
    }

    public static function forClient(Client $client): self
    {
        return self::firstOrCreate(['client_id' => $client->id], ['organization_id' => $client->organization_id]);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function googleAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class, 'google_ads_account_id');
    }

    /**
     * Senha do site para a conexão servidor → App (plugin do WordPress, Nonna
     * CMS, outra plataforma). Nasce na primeira vez que alguém pede.
     */
    public function siteSecret(): string
    {
        if (!$this->site_secret) {
            $this->forceFill(['site_secret' => Str::random(40)])->save();
        }

        return $this->site_secret;
    }

    /** Corta a senha antiga na hora: o site para de entregar até receber o código novo. */
    public function rotateSiteSecret(): string
    {
        $this->forceFill(['site_secret' => Str::random(40), 'site_secret_rotated_at' => now()])->save();

        return $this->site_secret;
    }

    /** O que se cola no site: "chave.senha" (64 caracteres). */
    public function connectionCode(): string
    {
        return $this->site_key . '.' . $this->siteSecret();
    }

    public function isTestMode(): bool
    {
        return filled($this->meta_test_event_code);
    }
}

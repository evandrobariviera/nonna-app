<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ClientCredential extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'platform',
        'platform_custom',
        'access_url',
        'username',
        'password',
        'notes',
        'created_by',
    ];

    public static array $platforms = [
        'site'               => 'Site / WordPress',
        'hospedagem'         => 'Hospedagem (cPanel / Hostinger)',
        'instagram'          => 'Instagram',
        'facebook'           => 'Facebook',
        'linkedin'           => 'LinkedIn',
        'tiktok'             => 'TikTok',
        'youtube'            => 'YouTube',
        'google_meu_negocio' => 'Google Meu Negócio',
        'google_ads'         => 'Google Ads',
        'meta_bm'            => 'Meta Business Manager',
        'email'              => 'E-mail',
        'outros'             => 'Outros',
    ];

    public function platformLabel(): string
    {
        if ($this->platform === 'outros' && $this->platform_custom) {
            return $this->platform_custom;
        }
        return self::$platforms[$this->platform] ?? $this->platform;
    }

    // Criptografa senha ao salvar
    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['password'] = $value ? Crypt::encryptString($value) : null;
    }

    // Descriptografa senha ao ler
    public function getPasswordAttribute(?string $value): ?string
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return null;
        }
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

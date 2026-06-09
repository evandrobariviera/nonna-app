<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAdAccount extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'platform',
        'platform_custom',
        'account_id',
        'account_name',
        'status',
        'notes',
        'created_by',
    ];

    public static array $platforms = [
        'meta_ads'            => 'Meta Ads (Facebook/Instagram)',
        'meta_bm'             => 'Meta Business Manager',
        'google_ads'          => 'Google Ads',
        'google_analytics'    => 'Google Analytics 4',
        'google_tag_manager'  => 'Google Tag Manager',
        'tiktok_ads'          => 'TikTok Ads',
        'linkedin_ads'        => 'LinkedIn Ads',
        'pinterest_ads'       => 'Pinterest Ads',
        'outros'              => 'Outros',
    ];

    public static array $statuses = [
        'ativo'    => ['label' => 'Ativo',    'color' => 'green'],
        'pausado'  => ['label' => 'Pausado',  'color' => 'orange'],
        'suspenso' => ['label' => 'Suspenso', 'color' => 'red'],
    ];

    public function platformLabel(): string
    {
        if ($this->platform === 'outros' && $this->platform_custom) {
            return $this->platform_custom;
        }
        return self::$platforms[$this->platform] ?? $this->platform;
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
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

<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Campanha completa criada pelo App na Meta (sempre nasce pausada).
 */
class MetaCampaignCreation extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'client_id', 'client_ad_account_id', 'kind', 'campaign_name', 'adset_name',
        'daily_budget', 'housing', 'audience', 'campaign_id', 'adset_id', 'meta_ad_creation_id',
        'status', 'error', 'ad_error', 'created_by', 'activated_by', 'activated_at',
    ];

    protected $casts = [
        'audience'     => 'array',
        'housing'      => 'boolean',
        'daily_budget' => 'decimal:2',
        'activated_at' => 'datetime',
    ];

    public static array $kinds = [
        'mensagens'      => ['label' => 'Mensagens (WhatsApp)',   'objective' => 'OUTCOME_ENGAGEMENT', 'goal' => 'CONVERSATIONS', 'destination' => 'WHATSAPP', 'tag' => 'ENG',  'result' => 'MENSAGENS'],
        'trafego'        => ['label' => 'Tráfego para o site',     'objective' => 'OUTCOME_TRAFFIC',    'goal' => 'LINK_CLICKS',   'destination' => 'WEBSITE',  'tag' => 'TRAF', 'result' => 'SITE'],
        'reconhecimento' => ['label' => 'Reconhecimento / alcance', 'objective' => 'OUTCOME_AWARENESS',  'goal' => 'REACH',         'destination' => null,       'tag' => 'REC',  'result' => 'ALCANCE'],
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class, 'client_ad_account_id');
    }

    public function adCreation(): BelongsTo
    {
        return $this->belongsTo(MetaAdCreation::class, 'meta_ad_creation_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function kindLabel(): string
    {
        return self::$kinds[$this->kind]['label'] ?? $this->kind;
    }

    public function statusLabel(): string
    {
        return MetaAdCreation::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return MetaAdCreation::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function managerUrl(): ?string
    {
        $act = $this->adAccount?->account_id;

        return $act && $this->campaign_id ? "https://adsmanager.facebook.com/adsmanager/manage/campaigns?act={$act}&selected_campaign_ids={$this->campaign_id}" : null;
    }
}

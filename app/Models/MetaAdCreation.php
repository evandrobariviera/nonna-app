<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anúncio criado na Meta a partir de uma arte aprovada (sempre nasce pausado).
 */
class MetaAdCreation extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'task_id', 'task_attachment_id', 'client_ad_account_id',
        'campaign_external_id', 'campaign_name', 'adset_external_id', 'adset_name', 'template_ad_id',
        'ad_id', 'creative_id', 'ad_name', 'status', 'message', 'headline', 'cta_type', 'link',
        'caption_changed', 'error', 'created_by', 'activated_by', 'activated_at',
    ];

    protected $casts = [
        'caption_changed' => 'boolean',
        'activated_at'    => 'datetime',
    ];

    public static array $statuses = [
        'pausado' => ['label' => 'Criado · pausado', 'color' => 'orange'],
        'ativo'   => ['label' => 'No ar',            'color' => 'green'],
        'erro'    => ['label' => 'Erro',             'color' => 'red'],
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(TaskAttachment::class, 'task_attachment_id');
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class, 'client_ad_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    /** Abre o anúncio no Gerenciador da Meta. */
    public function managerUrl(): ?string
    {
        $act = $this->adAccount?->account_id;

        return $act && $this->ad_id ? "https://adsmanager.facebook.com/adsmanager/manage/ads?act={$act}&selected_ad_ids={$this->ad_id}" : null;
    }
}

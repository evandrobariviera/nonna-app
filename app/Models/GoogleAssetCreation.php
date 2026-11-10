<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imagem de arte aprovada enviada a um grupo de recursos da Performance Max
 * (sempre nasce pausada).
 */
class GoogleAssetCreation extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'task_id', 'task_attachment_id', 'client_ad_account_id',
        'campaign_external_id', 'campaign_name', 'asset_group_id', 'asset_group_name',
        'asset_resource', 'link_resource', 'field_type', 'youtube_id', 'status', 'error',
        'created_by', 'activated_by', 'activated_at',
    ];

    protected $casts = ['activated_at' => 'datetime'];

    public static array $fieldLabels = [
        'MARKETING_IMAGE'          => 'Paisagem 1,91:1',
        'SQUARE_MARKETING_IMAGE'   => 'Quadrada 1:1',
        'PORTRAIT_MARKETING_IMAGE' => 'Vertical 4:5',
        'YOUTUBE_VIDEO'            => 'Vídeo do YouTube',
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
        return MetaAdCreation::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return MetaAdCreation::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function fieldLabel(): string
    {
        return self::$fieldLabels[$this->field_type] ?? $this->field_type;
    }
}

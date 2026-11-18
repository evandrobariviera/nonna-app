<?php

namespace App\Models;

use App\Traits\TenantViaParent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliverableFeedback extends Model
{
    use HasUuids, TenantViaParent;

    // Filtro de organização herdado do pai (ver TenantViaParent).
    public function tenantParentRelation(): string
    {
        return 'token';
    }

    protected $connection = 'pgsql';
    protected $table = 'deliverable_feedbacks';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'token_id', 'attachment_id', 'status', 'comment',
    ];

    public function token(): BelongsTo
    {
        return $this->belongsTo(TaskApprovalToken::class, 'token_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(TaskAttachment::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}

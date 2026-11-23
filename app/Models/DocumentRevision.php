<?php

namespace App\Models;

use App\Traits\TenantViaParent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma mudança num documento em campos (ver DocumentEditor). */
class DocumentRevision extends Model
{
    use HasUuids, TenantViaParent;

    protected $connection = 'pgsql';

    protected $fillable = ['document_id', 'user_id', 'actor', 'action', 'path', 'before', 'after'];

    protected $casts = [
        'before' => 'array',
        'after'  => 'array',
    ];

    public function tenantParentRelation(): string
    {
        return 'document';
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

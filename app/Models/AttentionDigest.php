<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

// "Seu foco" da Dashboard — ver App\Services\Dashboard\AttentionDigestService.
class AttentionDigest extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'user_id', 'generated_at', 'source',
        'opening', 'focus', 'can_wait', 'items', 'error',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'focus'        => 'array',
        'items'        => 'array',
    ];

    public static function latestFor(int $userId): ?self
    {
        return static::where('user_id', $userId)->latest('generated_at')->first();
    }
}

<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HelpArticle extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'title',
        'slug',
        'category',
        'body',
        'created_by',
        'updated_by',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Gera um slug único (por organização) a partir do título — igual pro artigo novo
    // e pra quando o título muda numa edição. Sufixo -2/-3/... só entra em caso de
    // colisão de verdade (dois artigos com título igual).
    public static function uniqueSlugFor(string $title, ?string $organizationId, ?string $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'artigo';
        $slug = $base;
        $i = 2;

        while (
            static::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}

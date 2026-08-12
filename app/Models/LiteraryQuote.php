<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LiteraryQuote extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'book', 'author', 'excerpt', 'justification',
        'source_url', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

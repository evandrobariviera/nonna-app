<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceDiagnosticPersona extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'diagnostic_id',
        'position',
        'tag',
        'name',
        'profile',
        'behavior',
        'evidence',
    ];

    public function diagnostic(): BelongsTo
    {
        return $this->belongsTo(ServiceDiagnostic::class, 'diagnostic_id');
    }
}

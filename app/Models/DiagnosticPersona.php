<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticPersona extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'diagnostic_id',
        'position',
        'name',
        'age_range',
        'profession',
        'income',
        'location',
        'what_they_seek',
        'frustrations',
        'decision_process',
        'objections',
    ];

    public function diagnostic(): BelongsTo
    {
        return $this->belongsTo(ClientDiagnostic::class, 'diagnostic_id');
    }
}

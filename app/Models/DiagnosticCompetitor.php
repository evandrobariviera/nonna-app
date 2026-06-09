<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticCompetitor extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'diagnostic_id',
        'position',
        'name',
        'main_channels',
        'positioning',
        'strengths',
        'vulnerability',
    ];

    public function diagnostic(): BelongsTo
    {
        return $this->belongsTo(ClientDiagnostic::class, 'diagnostic_id');
    }
}

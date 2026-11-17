<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Crédito extra do Agente Nonna liberado por um admin numa semana. Ver AgentCredits. */
class AgentCreditGrant extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = ['user_id', 'week_start', 'credits', 'note', 'granted_by'];

    protected $casts = ['week_start' => 'date'];

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}

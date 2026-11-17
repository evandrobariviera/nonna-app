<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Limite semanal próprio de créditos do Agente Nonna (sem linha = padrão). Ver AgentCredits. */
class AgentCreditLimit extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = ['user_id', 'weekly_credits', 'updated_by'];
}

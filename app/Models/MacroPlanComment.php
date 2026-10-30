<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Comentário da discussão do planejamento (ver MacroPlanCommentController). Nasce interno; a equipe pode marcar "Visível pro cliente".
class MacroPlanComment extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = ['organization_id', 'macro_plan_id', 'user_id', 'body', 'visible_to_client'];

    protected $casts = [
        'visible_to_client' => 'boolean',
    ];

    public function macroPlan(): BelongsTo
    {
        return $this->belongsTo(MacroPlan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

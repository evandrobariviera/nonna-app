<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Comentário da discussão do planejamento (ver MacroPlanCommentController). Só interno.
class MacroPlanComment extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = ['organization_id', 'macro_plan_id', 'user_id', 'body'];

    public function macroPlan(): BelongsTo
    {
        return $this->belongsTo(MacroPlan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

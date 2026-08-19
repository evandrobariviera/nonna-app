<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLeadOpportunityNote extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_lead_opportunity_id',
        'user_id',
        'contact_id',
        'body',
        'from_stage',
        'to_stage',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(ClientLeadOpportunity::class, 'client_lead_opportunity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    // Autor — sempre exatamente um dos dois (user_id XOR contact_id, garantido
    // por constraint no banco), mesmo padrão de TaskComment::commenter().
    public function author(): User|Contact|null
    {
        return $this->user ?? $this->contact;
    }

    public function isStageChange(): bool
    {
        return $this->to_stage !== null;
    }
}

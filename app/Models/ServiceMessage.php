<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceMessage extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'conversation_id',
        'external_message_id',
        'direction',
        'sent_via_api',
        'sender_name',
        'body',
        'media_url',
        'sent_at',
        'raw_payload',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'raw_payload'  => 'array',
        'sent_via_api' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ServiceConversation::class, 'conversation_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === 'in';
    }
}

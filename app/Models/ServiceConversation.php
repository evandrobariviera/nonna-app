<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceConversation extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'client_integration_id',
        'external_thread_id',
        'contact_name',
        'contact_phone',
        'started_at',
        'last_message_at',
        'message_count',
    ];

    protected $casts = [
        'started_at'      => 'datetime',
        'last_message_at' => 'datetime',
        'message_count'   => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ClientIntegration::class, 'client_integration_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ServiceMessage::class, 'conversation_id')->orderBy('sent_at');
    }
}

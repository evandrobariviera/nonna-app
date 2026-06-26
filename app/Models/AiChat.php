<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiChat extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = ['entity_type', 'entity_id'];

    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'chat_id')->orderBy('created_at');
    }
}

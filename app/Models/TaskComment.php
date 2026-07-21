<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = ['task_id', 'user_id', 'contact_id', 'body', 'visible_to_client'];

    protected $casts = [
        'visible_to_client' => 'boolean',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    // Autor do comentário — sempre exatamente um dos dois (user_id XOR
    // contact_id, garantido por constraint no banco).
    public function commenter(): User|Contact|null
    {
        return $this->user ?? $this->contact;
    }
}

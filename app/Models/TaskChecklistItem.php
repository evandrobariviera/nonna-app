<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklistItem extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'task_id', 'source_comment_id', 'title', 'assigned_to', 'done', 'done_at', 'created_by',
    ];

    protected $casts = [
        'done'    => 'boolean',
        'done_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function sourceComment(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'source_comment_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

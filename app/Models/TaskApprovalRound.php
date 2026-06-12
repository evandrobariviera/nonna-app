<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskApprovalRound extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'task_id', 'round_number', 'submitted_by', 'submitted_at',
        'status', 'notes', 'resolved_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'resolved_at'  => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(TaskApprovalToken::class, 'round_id');
    }

    public function deliverables()
    {
        return TaskAttachment::where('task_id', $this->task_id)
            ->where('is_deliverable', true)
            ->where('round_number', $this->round_number)
            ->orderBy('created_at')
            ->get();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['approved', 'changes_requested', 'cancelled']);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'           => 'Aguardando',
            'approved'          => 'Aprovado',
            'changes_requested' => 'Ajustes Solicitados',
            'cancelled'         => 'Cancelado',
            default             => $this->status,
        };
    }
}

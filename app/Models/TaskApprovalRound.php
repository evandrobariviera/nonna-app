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
        'task_id', 'round_number', 'submitted_by', 'submitted_at', 'sent_at',
        'status', 'notes', 'caption', 'resolved_at', 'handled_at',
        'portal_decided_by', 'portal_decided_by_contact_id', 'portal_decision', 'portal_comment', 'portal_decided_at',
    ];

    protected $casts = [
        'submitted_at'      => 'datetime',
        'sent_at'           => 'datetime',
        'resolved_at'       => 'datetime',
        'handled_at'        => 'datetime',
        'portal_decided_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function portalDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'portal_decided_by');
    }

    public function portalDecidedByContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'portal_decided_by_contact_id');
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

    // "Tratada" pela agência (ex: já roteou a tarefa de volta pra Sprint depois de
    // um ajuste pedido) — só afeta visibilidade na Central de Aprovações, não é
    // um novo status da rodada. Ver migration 2026_07_30_000007.
    public function isHandled(): bool
    {
        return $this->handled_at !== null;
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

    // Igual a statusLabel(), mas distingue "ainda não mandamos pro cliente"
    // de "mandamos, agora é esperar resposta" — sent_at é um estado à parte
    // do status da rodada (aprovado/pendente/ajustes), não um novo status.
    public function displayStatusLabel(): string
    {
        if ($this->status === 'pending' && !$this->sent_at) {
            return 'Aguardando Envio';
        }

        return $this->statusLabel();
    }
}

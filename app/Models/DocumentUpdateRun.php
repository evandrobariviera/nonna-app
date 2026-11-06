<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma análise "Atualizar documento com reunião" (DocumentUpdateService): a IA leu a reunião
 * e o documento e deixou sugestões. Nada muda no documento até alguém aceitar — aí vira
 * uma versão nova (DocumentEditService) e o run fica "aplicado".
 *
 * suggestions: [{id, secao, tipo, titulo, motivo, evidencia, trecho_atual, trecho_novo,
 * aplicavel (achou o trecho uma vez só no documento), status (null | aplicada | falhou)}]
 */
class DocumentUpdateRun extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'owner_type', 'owner_id', 'meeting_id', 'base_attachment_id', 'instruction',
        'status', 'summary', 'strategic_review', 'suggestions', 'data_after', 'data_reason', 'open_points', 'error',
        'applied_attachment_id', 'requested_by', 'applied_by', 'applied_at',
    ];

    protected $casts = [
        'suggestions' => 'array',
        'strategic_review' => 'array',
        'data_after'  => 'array',
        'open_points' => 'array',
        'applied_at'  => 'datetime',
    ];

    public function owner(): MacroPlan|Project|null
    {
        return $this->owner_type === 'macro_plan'
            ? MacroPlan::find($this->owner_id)
            : Project::find($this->owner_id);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    // Job tem timeout de 600s: "processando" há mais que isso = o worker morreu no meio
    // (ex.: deploy reiniciou o container) e ninguém vai concluir a análise.
    public const STUCK_AFTER_MINUTES = 15;

    public function isProcessing(): bool
    {
        return $this->status === 'processando';
    }

    public function isStuck(): bool
    {
        return $this->isProcessing() && $this->created_at?->lt(now()->subMinutes(self::STUCK_AFTER_MINUTES));
    }

    /** Análise presa vira "erro" — libera o "Tentar de novo" (a trava de custo não conta erro). */
    public function releaseIfStuck(): void
    {
        if ($this->isStuck()) {
            $this->update([
                'status' => 'erro',
                'error'  => 'A análise foi interrompida no meio (o sistema reiniciou enquanto a IA trabalhava) e não chegou a terminar. É só tentar de novo.',
            ]);
        }
    }

    public function isReady(): bool
    {
        return $this->status === 'pronto';
    }
}

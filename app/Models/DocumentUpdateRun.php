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
        'comments', 'revision', 'history',
    ];

    protected $casts = [
        'suggestions' => 'array',
        'strategic_review' => 'array',
        'data_after'  => 'array',
        'open_points' => 'array',
        'applied_at'  => 'datetime',
        'comments'    => 'array',
        'history'     => 'array',
        'revision'    => 'integer',
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
    // (ex.: deploy reiniciou o container) e ninguém vai concluir a análise. Conta do
    // updated_at (entrar em "processando" atualiza): a rodada de revisão começa depois da criação.
    public const STUCK_AFTER_MINUTES = 15;

    public function isProcessing(): bool
    {
        return $this->status === 'processando';
    }

    public function isStuck(): bool
    {
        return $this->isProcessing() && $this->updated_at?->lt(now()->subMinutes(self::STUCK_AFTER_MINUTES));
    }

    /** Análise presa sai de "processando" — libera o "Tentar de novo" (ou volta à rodada anterior). */
    public function releaseIfStuck(): void
    {
        if ($this->isStuck()) {
            $this->failRound('A análise foi interrompida no meio (o sistema reiniciou enquanto a IA trabalhava) e não chegou a terminar. É só tentar de novo.');
        }
    }

    /**
     * A IA não entregou. Na 1ª análise vira "erro" (a trava de custo não conta erro). Numa rodada
     * de revisão, volta o resultado da rodada anterior com os comentários — nada se perde e a
     * rodada não conta no limite.
     */
    public function failRound(string $message): void
    {
        $fresh = $this->fresh() ?? $this;
        $history = $fresh->history ?? [];

        if ($fresh->revision > 0 && $history) {
            $previous = array_pop($history);
            $fresh->update([
                'status'           => 'pronto',
                'summary'          => $previous['summary'] ?? null,
                'strategic_review' => $previous['strategic_review'] ?? null,
                'suggestions'      => $previous['suggestions'] ?? null,
                'data_after'       => $previous['data_after'] ?? null,
                'data_reason'      => $previous['data_reason'] ?? null,
                'open_points'      => $previous['open_points'] ?? null,
                'comments'         => $previous['comments'] ?? null,
                'history'          => $history ?: null,
                'revision'         => $fresh->revision - 1,
                'error'            => 'A revisão com os comentários não terminou (' . mb_substr($message, 0, 400) . '). Voltou a versão anterior — dá para refazer.',
            ]);
        } else {
            $fresh->update(['status' => 'erro', 'error' => mb_substr($message, 0, 1000)]);
        }

        $this->setRawAttributes($fresh->getAttributes(), true);
    }

    public function isReady(): bool
    {
        return $this->status === 'pronto';
    }

    /** Comentário da rodada atual: 'geral' ou "s:{id}" (sugestão), "m:{i}" (movimento), "p:{i}" (ponto em aberto). */
    public function comment(string $key): string
    {
        return (string) ($this->comments[$key] ?? '');
    }

    public function hasComments(): bool
    {
        return collect($this->comments ?? [])->contains(fn ($c) => trim((string) $c) !== '');
    }
}

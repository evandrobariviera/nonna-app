<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma resposta da IA numa rodada (pergunta × repetição). */
class CommerceAiAnswer extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = ['round_id', 'question_index', 'repetition', 'answer', 'names', 'sources', 'error'];

    protected $casts = [
        'names'   => 'array',
        'sources' => 'array',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(CommerceAiRound::class, 'round_id');
    }
}

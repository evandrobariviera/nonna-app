<?php

namespace App\Jobs;

use App\Models\CommerceAiRound;
use App\Services\Commerce\AiRoundRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Uma resposta de uma rodada de IA (pergunta × repetição): ChatGPT com busca na web, até uns
 * 2 minutos. Fila "prospecting" (o worker só pega depois da "default"). Uma tentativa só:
 * repetir custaria de novo; o erro fica registrado na resposta e a rodada fecha mesmo assim.
 */
class AskCommerceAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 320; // abaixo do retry_after (700) da fila

    public function __construct(public string $roundId, public int $question, public int $repetition)
    {
        $this->onQueue('prospecting');
    }

    public function handle(AiRoundRunner $runner): void
    {
        $round = CommerceAiRound::withoutGlobalScopes()->find($this->roundId);
        if ($round && in_array($round->status, ['queued', 'running'], true)) {
            $runner->ask($round, $this->question, $this->repetition);
        }
    }
}

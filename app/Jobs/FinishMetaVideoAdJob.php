<?php

namespace App\Jobs;

use App\Models\MetaAdCreation;
use App\Services\AdSync\MetaAdCreator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Criativos — vídeo: confere a cada 20 s se a Meta terminou de processar o
 * vídeo e então cria o anúncio PAUSADO. Desiste depois de ~15 minutos.
 */
class FinishMetaVideoAdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Vale mais que o --tries do worker: cada "ainda processando" conta uma tentativa. */
    public int $tries = 45;
    public int $timeout = 80;

    public function __construct(public string $creationId) {}

    public function handle(MetaAdCreator $creator): void
    {
        $creation = MetaAdCreation::find($this->creationId);
        if (!$creation || $creation->status !== 'processando') {
            return;
        }

        if ($creator->finishVideo($creation) === 'aguardando') {
            if ($this->attempts() >= $this->tries) {
                $creation->update(['status' => 'erro', 'error' => 'A Meta demorou mais de 15 minutos para processar o vídeo. Tente de novo ou suba pelo Gerenciador.']);

                return;
            }
            $this->release(20);
        }
    }

    public function failed(\Throwable $e): void
    {
        MetaAdCreation::where('id', $this->creationId)->where('status', 'processando')
            ->update(['status' => 'erro', 'error' => 'Não foi possível terminar o anúncio do vídeo: ' . mb_substr($e->getMessage(), 0, 200)]);
    }
}

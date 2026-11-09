<?php

namespace App\Jobs;

use App\Services\AdSync\AdAccountConnectionChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Botão "Verificar agora" de Contas e conexões: roda a verificação em segundo
 * plano (são ~1 min de chamadas às APIs). Único na fila: clicar duas vezes
 * não dispara duas verificações.
 */
class CheckAdAccountConnectionsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;
    public int $uniqueFor = 600;

    public function handle(AdAccountConnectionChecker $checker): void
    {
        $checker->checkAll();
    }
}

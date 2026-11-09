<?php

namespace App\Console\Commands;

use App\Services\AdSync\AdAccountConnectionChecker;
use Illuminate\Console\Command;

/**
 * Contas e conexões: confere Página/Instagram/acesso/UTM de todas as contas
 * de anúncio de clientes ativos. Roda todo dia depois da sincronização.
 */
class CheckAdAccountConnections extends Command
{
    protected $signature = 'ad-accounts:check-connections';

    protected $description = 'Confere Página, Instagram, acesso e UTM das contas de anúncio (Central de Campanhas)';

    public function handle(AdAccountConnectionChecker $checker): int
    {
        $counts = $checker->checkAll();
        $this->info('Conferidas: ' . collect($counts)->map(fn ($n, $s) => "{$s}={$n}")->implode(' · '));

        return self::SUCCESS;
    }
}

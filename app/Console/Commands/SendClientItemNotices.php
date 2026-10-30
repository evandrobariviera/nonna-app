<?php

namespace App\Console\Commands;

use App\Services\ClientItemNoticeService;
use Illuminate\Console\Command;

/**
 * Envia os avisos de planejamento/campanha/projeto em execução que já passaram da
 * espera (ver ClientItemNotice). Fora do horário comercial eles aguardam a manhã,
 * pra não chegar WhatsApp no cliente de madrugada.
 */
class SendClientItemNotices extends Command
{
    protected $signature = 'client-notices:send';

    protected $description = 'Avisa os clientes dos planejamentos, campanhas e projetos que entraram em execução';

    private const HOURS = [8, 20];

    public function handle(ClientItemNoticeService $notices): int
    {
        [$from, $to] = self::HOURS;

        if (now()->hour < $from || now()->hour >= $to) {
            return self::SUCCESS;
        }

        $this->info($notices->sendDue() . ' aviso(s) processado(s).');

        return self::SUCCESS;
    }
}

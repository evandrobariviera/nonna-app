<?php

namespace App\Jobs;

use App\Models\ClientLeadOpportunity;
use App\Services\Tracking\ConversionSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Lead novo (ou lead bom / venda) → conversões para Meta, Google Ads e GA4,
 * em segundo plano, para a chegada do lead nunca esperar as plataformas.
 */
class SendLeadConversionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 80;
    public array $backoff = [60, 300];

    public function __construct(public string $opportunityId, public string $eventName = 'Lead', public ?float $value = null) {}

    public function handle(ConversionSender $sender): void
    {
        if ($opp = ClientLeadOpportunity::find($this->opportunityId)) {
            $sender->send($opp, $this->eventName, $this->value);
        }
    }
}

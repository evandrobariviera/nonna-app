<?php

namespace App\Jobs;

use App\Models\CommercePlace;
use App\Services\Commerce\PlaceCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Coleta de uma empresa (Google + site + PageSpeed, até ~80 s). Fila "prospecting", que o
 * worker só pega depois da "default": uma coleta grande não atrasa ATA, avisos e envios.
 */
class CollectCommercePlaceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 88;

    /** $allowPaid: admin confirmou coletar acima da franquia grátis do Google (GoogleUsage). */
    public function __construct(public string $placeId, public bool $allowPaid = false)
    {
        $this->onQueue('prospecting');
    }

    public function handle(PlaceCollector $collector): void
    {
        $place = CommercePlace::withoutGlobalScopes()->find($this->placeId);
        if ($place && $place->status !== 'opted_out') {
            $collector->collect($place, allowPaid: $this->allowPaid);
        }
    }
}

<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        private readonly string $url,
        private readonly string $event,
        private readonly array  $payload,
        private readonly string $organizationId,
    ) {}

    public function handle(): void
    {
        Http::timeout(20)
            ->retry(2, 1000)
            ->post($this->url, [
                'event'           => $this->event,
                'organization_id' => $this->organizationId,
                'payload'         => $this->payload,
                'fired_at'        => now()->toISOString(),
            ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Webhook falhou [{$this->event}] → {$this->url}: {$e->getMessage()}");
    }
}

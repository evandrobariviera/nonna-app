<?php

namespace App\Jobs;

use App\Models\CommerceScan;
use App\Services\Commerce\ScanRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Varredura da prospecção em segundo plano (até ~15 buscas no Google). */
class RunCommerceScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 85;

    public function __construct(public string $scanId) {}

    public function handle(ScanRunner $runner): void
    {
        $scan = CommerceScan::withoutGlobalScopes()->find($this->scanId);
        if ($scan && in_array($scan->status, ['queued', 'running'], true)) {
            $runner->run($scan);
        }
    }

    public function failed(\Throwable $e): void
    {
        CommerceScan::withoutGlobalScopes()->whereKey($this->scanId)
            ->update(['status' => 'error', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
    }
}

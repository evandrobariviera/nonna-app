<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\User;
use App\Services\Dashboard\AttentionDigestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// "Seu foco" de UMA pessoa — um job por pessoa pra uma IA lenta não segurar as outras
// (worker roda com --timeout=90). Falha da IA não derruba o job: o serviço cai nas regras.
class GenerateAttentionDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 85;

    public function __construct(public int $userId, public string $organizationId) {}

    public function handle(AttentionDigestService $service): void
    {
        $organization = Organization::find($this->organizationId);
        $user = User::find($this->userId);
        if (!$organization || !$user) {
            return;
        }

        app()->instance('currentOrganization', $organization);
        $service->generate($user, $organization->id);
    }
}

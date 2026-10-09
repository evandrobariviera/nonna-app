<?php

namespace App\Console\Commands;

use App\Jobs\GenerateAttentionDigestJob;
use App\Models\AttentionDigest;
use App\Models\Organization;
use App\Models\OrganizationUser;
use Illuminate\Console\Command;

// "Seu foco" (AttentionDigestService) — agendado 07:30 e 12:00 em dia útil (routes/console.php).
class GenerateAttentionDigests extends Command
{
    protected $signature = 'attention:generate-digests {--user= : Só esta pessoa (id), na hora, sem fila}';

    protected $description = 'Gera o resumo "Seu foco" (onde pôr a atenção) de cada pessoa pra Dashboard';

    public function handle(): int
    {
        $count = 0;

        foreach (Organization::whereIn('status', ['trial', 'active'])->get() as $organization) {
            $userIds = OrganizationUser::where('organization_id', $organization->id)
                ->when($this->option('user'), fn ($q, $id) => $q->where('user_id', $id))
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $this->option('user')
                    ? GenerateAttentionDigestJob::dispatchSync((int) $userId, $organization->id)
                    : GenerateAttentionDigestJob::dispatch((int) $userId, $organization->id);
                $count++;
            }
        }

        // Só o último resumo aparece; guarda 30 dias pra consulta e descarta o resto.
        AttentionDigest::withoutGlobalScopes()->where('generated_at', '<', now()->subDays(30))->delete();

        $this->info("Resumos enfileirados/gerados: {$count}.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Leads\LeadQualifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Avalia pela régua do cliente os leads novos e os que tiveram conversa nova
 * (LeadQualifier::pending). Roda de hora em hora; --limit segura o custo da
 * primeira passada, que pega o histórico inteiro do cliente recém-configurado.
 */
class QualifyLeads extends Command
{
    protected $signature = 'leads:qualify
        {--client= : Só um cliente (id)}
        {--limit=150 : Máximo de leads avaliados por rodada}
        {--dry : Só mostra quantos seriam avaliados}';

    protected $description = 'IA avalia os leads da Central de Leads pela régua de cada cliente';

    public function handle(LeadQualifier $qualifier): int
    {
        $query = LeadQualifier::pending()
            ->when($this->option('client'), fn ($q, $id) => $q->where('clients.id', $id));

        if ($this->option('dry')) {
            $this->info((clone $query)->count() . ' lead(s) para avaliar. Simulação — nada foi gravado.');

            return self::SUCCESS;
        }

        $agent = LeadQualifier::agent();
        if (!$agent) {
            $this->warn('Agente de avaliação de lead inativo — nada a fazer.');

            return self::SUCCESS;
        }

        $done = $failed = 0;
        foreach ($query->limit((int) $this->option('limit'))->get() as $opportunity) {
            try {
                $qualifier->evaluate($opportunity, $agent);
                $done++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('leads:qualify falhou', ['opportunity' => $opportunity->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("{$done} lead(s) avaliado(s)" . ($failed ? ", {$failed} com erro (ver log)" : '') . '.');

        return self::SUCCESS;
    }
}

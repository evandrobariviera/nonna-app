<?php

namespace App\Console\Commands;

use App\Jobs\AutomationJob;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\MacroPlan;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class CheckAutomationDateTriggers extends Command
{
    protected $signature = 'automations:check-date-triggers';

    protected $description = 'Dispara automações do tipo "Data alcançada" pras entidades cuja data (com antecedência configurada) cai em hoje';

    public function handle(): int
    {
        $automations = Automation::where('trigger_type', 'date_reached')
            ->whereIn('entity_type', ['task', 'ticket', 'macro_plan'])
            ->where('is_active', true)
            ->get();

        $dispatched = 0;

        foreach ($automations as $automation) {
            $dateField = $automation->trigger_config['date_field'] ?? null;
            if (!array_key_exists($dateField, Automation::dateFieldsFor($automation->entity_type))) {
                continue;
            }

            // offset_days = "dispara N dias ANTES da data do campo" — a data-alvo da busca
            // é hoje + offset (offset=0 mantém o comportamento antigo, "dispara no dia").
            $offsetDays = (int) ($automation->trigger_config['offset_days'] ?? 0);
            $targetDate = today()->addDays($offsetDays);

            $entities = $this->entitiesFor($automation->entity_type, $dateField, $targetDate);

            foreach ($entities as $entity) {
                if (!$automation->conditionsMatch($entity)) {
                    continue;
                }

                // Idempotência: não dispara de novo pro mesmo registro se o comando já
                // rodou com sucesso hoje (proteção contra o schedule:work rodar 2x).
                $alreadyFiredToday = AutomationLog::where('automation_id', $automation->id)
                    ->where('entity_type', $automation->entity_type)
                    ->where('entity_id', $entity->id)
                    ->where('status', 'success')
                    ->whereDate('ran_at', today())
                    ->exists();

                if ($alreadyFiredToday) {
                    continue;
                }

                AutomationJob::dispatch($automation, $automation->entity_type, $entity->id, ['date_field' => $dateField]);
                $dispatched++;
            }
        }

        $this->info("Automações de data disparadas: {$dispatched}.");

        return self::SUCCESS;
    }

    /**
     * @return iterable<Model>
     */
    private function entitiesFor(string $entityType, string $dateField, \Illuminate\Support\Carbon $targetDate): iterable
    {
        return match ($entityType) {
            // Ticket e Tarefa são a mesma tabela (Task.is_ticket) — a automação já
            // escolheu qual das duas entidades quer, então filtra aqui pra não misturar.
            'task', 'ticket' => Task::whereDate($dateField, $targetDate)
                ->where('is_ticket', $entityType === 'ticket')
                ->get(),
            'macro_plan' => MacroPlan::whereDate($dateField, $targetDate)->get(),
            default => [],
        };
    }
}

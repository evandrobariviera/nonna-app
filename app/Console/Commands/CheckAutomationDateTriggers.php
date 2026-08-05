<?php

namespace App\Console\Commands;

use App\Jobs\AutomationJob;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Task;
use Illuminate\Console\Command;

class CheckAutomationDateTriggers extends Command
{
    protected $signature = 'automations:check-date-triggers';

    protected $description = 'Dispara automações do tipo "Data alcançada" (vencimento/aprovação/publicação) pras tarefas que chegam na data hoje';

    public function handle(): int
    {
        $automations = Automation::where('trigger_type', 'date_reached')
            ->whereIn('entity_type', ['task', 'ticket'])
            ->where('is_active', true)
            ->get();

        $dispatched = 0;

        foreach ($automations as $automation) {
            $dateField = $automation->trigger_config['date_field'] ?? null;
            if (!array_key_exists($dateField, Automation::$dateFields)) {
                continue;
            }

            // Ticket e Tarefa são a mesma tabela (Task.is_ticket) — a automação já escolheu
            // qual das duas entidades quer, então filtra aqui pra não misturar as duas.
            $tasks = Task::whereDate($dateField, today())
                ->where('is_ticket', $automation->entity_type === 'ticket')
                ->get();

            foreach ($tasks as $task) {
                if (!$automation->conditionsMatch($task)) {
                    continue;
                }

                // Idempotência: não dispara de novo pra mesma tarefa se o comando já rodou
                // com sucesso hoje (proteção contra o schedule:work rodar mais de uma vez).
                $alreadyFiredToday = AutomationLog::where('automation_id', $automation->id)
                    ->where('entity_type', $automation->entity_type)
                    ->where('entity_id', $task->id)
                    ->where('status', 'success')
                    ->whereDate('ran_at', today())
                    ->exists();

                if ($alreadyFiredToday) {
                    continue;
                }

                AutomationJob::dispatch($automation, $automation->entity_type, $task->id, ['date_field' => $dateField]);
                $dispatched++;
            }
        }

        $this->info("Automações de data disparadas: {$dispatched}.");

        return self::SUCCESS;
    }
}

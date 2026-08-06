<?php

namespace App\Jobs;

use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Task;
use App\Models\Project;
use App\Models\AdCampaign;
use App\Models\AiAgent;
use App\Models\MacroPlan;
use App\Models\Meeting;
use App\Models\Opportunity;
use App\Models\FunctionalRole;
use App\Models\Sector;
use App\Models\User;
use App\Services\AiService;
use App\Services\ContextResolver;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutomationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        public readonly Automation $automation,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly array $changeData = [],
    ) {}

    public function handle(): void
    {
        $startedAt = now();

        $log = AutomationLog::create([
            'automation_id' => $this->automation->id,
            'entity_type'   => $this->entityType,
            'entity_id'     => $this->entityId,
            'status'        => 'running',
            'ran_at'        => $startedAt,
        ]);

        try {
            $entity = $this->resolveEntity();

            if (!$entity) {
                throw new \RuntimeException("Entidade {$this->entityType}:{$this->entityId} não encontrada.");
            }

            $context = ContextResolver::for($entity);
            $output  = $this->executeAction($entity, $context);

            $log->update([
                'status'         => 'success',
                'input_snapshot' => $context,
                'output'         => $output,
                'duration_ms'    => (int) round(abs(now()->diffInMilliseconds($startedAt))),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms'   => (int) round(abs(now()->diffInMilliseconds($startedAt))),
            ]);

            Log::error("AutomationJob falhou [{$this->automation->name}]", [
                'error'       => $e->getMessage(),
                'entity_type' => $this->entityType,
                'entity_id'   => $this->entityId,
            ]);

            throw $e;
        }
    }

    private function executeAction(mixed $entity, array $context): string
    {
        $config = $this->automation->action_config ?? [];

        return match ($this->automation->action_type) {
            'run_ai_agent'      => $this->runAiAgent($config, $context),
            'send_webhook'      => $this->sendWebhook($config, $context, $entity),
            'update_field'      => $this->updateField($config, $entity),
            'send_notification' => $this->sendNotification($config, $entity, $context),
            'create_record'     => $this->createRecord($config, $context, $entity),
            'create_macroplan_review' => $this->createMacroplanReview($config, $entity),
            default             => throw new \RuntimeException("Tipo de ação desconhecido: {$this->automation->action_type}"),
        };
    }

    private function runAiAgent(array $config, array $context): string
    {
        $agent = AiAgent::findOrFail($config['agent_id']);

        $userMessage = $config['user_message']
            ?? "Analise o contexto e execute sua função.";

        return app(AiService::class)->run(
            agent: $agent,
            userMessage: $userMessage,
            context: $context,
            trigger: "automation:{$this->automation->id}",
        );
    }

    private function sendWebhook(array $config, array $context, mixed $entity): string
    {
        $url    = $config['url'] ?? throw new \RuntimeException('Webhook sem URL configurada.');
        $method = strtolower($config['method'] ?? 'post');

        $payload = array_merge($context, [
            'automation_id' => $this->automation->id,
            'entity_type'   => $this->entityType,
            'entity_id'     => $this->entityId,
            'change_data'   => $this->changeData,
        ]);

        $response = Http::timeout(30)->$method($url, $payload);

        return "Webhook enviado. Status: {$response->status()}";
    }

    private function updateField(array $config, mixed $entity): string
    {
        $field = $config['field'] ?? throw new \RuntimeException('update_field sem campo configurado.');
        $value = $config['value'] ?? null;

        $entity->update([$field => $value]);

        return "Campo '{$field}' atualizado para '{$value}'.";
    }

    // Cria uma Tarefa avulsa ou um Ticket (mesma tabela, Task.is_ticket) como resultado da
    // automação — sem projeto (fica avulsa, mesmo padrão de origin=roadmap/ticket já usado no
    // sistema). tasks.client_id é NOT NULL no banco, então sem cliente resolvido cai no cliente
    // interno (clients.is_internal) — o mesmo sinal que Task::scopePendente() já usa pra jogar
    // a tarefa na fila de Pendências de Cadastro, sem precisar de tratamento de erro novo aqui.
    private function createRecord(array $config, array $context, mixed $entity): string
    {
        $isTicket = ($config['record_type'] ?? 'ticket') === 'ticket';

        $clientId = $config['client_id'] ?? null;
        if ($clientId === 'inherit') {
            $clientId = $entity->client_id ?? null;
        }
        if (!$clientId) {
            $clientId = \App\Models\Client::where('is_internal', true)->value('id');
        }

        // Job roda em background (sem middleware de tenant), então o trait Tenantable não tem
        // organização atual pra herdar sozinho — resolve explicitamente aqui, priorizando a
        // organização da própria entidade que disparou a automação.
        $organizationId = $entity->organization_id ?? $this->automation->createdBy?->organizations()->value('organizations.id');

        $task = Task::create([
            'organization_id' => $organizationId,
            'title'       => $this->interpolate($config['title'] ?? 'Nova tarefa', $context),
            'description' => $this->interpolate($config['description'] ?? '', $context),
            'task_type'   => $config['task_type'] ?? 'estrategia',
            'client_id'   => $clientId,
            'is_ticket'   => $isTicket,
            'origin'      => 'automation',
            'status'      => 'backlog',
            'due_date'    => !empty($config['due_in_days']) ? now()->addDays((int) $config['due_in_days']) : null,
            'created_by'  => $this->automation->created_by,
        ]);

        return "Registro criado: {$task->id} ({$task->title}).";
    }

    // Cria o Macroplanejamento a partir de uma Reunião de planejamento realizada, linka a
    // reunião original a ele, agenda a Reunião de Revisão Interna pro próximo dia útil
    // (já linkada ao mesmo Macro) e notifica o Papel Funcional configurado. Ação
    // específica (não genérica como create_record) por decisão de escopo: encadear a
    // criação de 2 registros diferentes, um referenciando o outro, não cabe num
    // action_config genérico sem construir um motor de ações em sequência — combinado
    // que essa automação em particular vem pronta no código, só o gatilho é configurável.
    private function createMacroplanReview(array $config, mixed $entity): string
    {
        if (!$entity instanceof Meeting) {
            throw new \RuntimeException('create_macroplan_review só funciona com entidade Reunião.');
        }

        // Idempotente: se o status oscilar de novo pra "realizada", não recria um
        // segundo Macro/Reunião pra mesma reunião original.
        if ($entity->macro_plan_id) {
            return "Reunião já vinculada ao Macroplanejamento {$entity->macro_plan_id} — nada feito.";
        }

        if (!$entity->client_id) {
            throw new \RuntimeException('Reunião sem cliente vinculado — não é possível criar o Macroplanejamento.');
        }

        $entity->loadMissing('client');

        $organizationId = $entity->organization_id ?? $this->automation->createdBy?->organizations()->value('organizations.id');

        $macroPlan = MacroPlan::create([
            'organization_id' => $organizationId,
            'client_id'       => $entity->client_id,
            'responsible_id'  => $entity->organized_by,
            'created_by'      => $this->automation->created_by,
            'title'           => $entity->client->displayName() . ' — Planejamento ' . now()->format('m/Y'),
            'period_start'    => now(),
            'period_end'      => now()->addDays(89),
            'status'          => 'em_planejamento',
        ]);

        $entity->update(['macro_plan_id' => $macroPlan->id]);

        // Próximo dia útil (pula sábado/domingo), no mesmo horário da reunião original.
        $reviewDate = now()->addDay();
        while ($reviewDate->isWeekend()) {
            $reviewDate->addDay();
        }
        $reviewDate->setTimeFromTimeString($entity->scheduled_at->format('H:i:s'));

        $reviewMeeting = Meeting::create([
            'organization_id'  => $organizationId,
            'title'            => 'Revisão Interna — ' . $entity->client->displayName(),
            'type'             => 'revisao_interna',
            'modality'         => $entity->modality,
            'status'           => 'agendada',
            'client_id'        => $entity->client_id,
            'macro_plan_id'    => $macroPlan->id,
            'organized_by'     => $entity->organized_by,
            'created_by'       => $this->automation->created_by,
            'scheduled_at'     => $reviewDate,
            'duration_minutes' => $entity->duration_minutes,
        ]);

        $role = !empty($config['role']) ? FunctionalRole::where('key', $config['role'])->first() : null;
        $recipients = $role ? $role->users : collect();

        if ($recipients->isNotEmpty()) {
            $reviewMeeting->participants()->sync($recipients->pluck('id'));

            app(NotificationService::class)->notifyUsers(
                $recipients,
                'automation',
                'Revisão interna agendada — ' . $macroPlan->title,
                'Reunião de revisão interna marcada para ' . $reviewMeeting->scheduled_at->format('d/m/Y H:i') . '.',
                route('meetings.show', $reviewMeeting),
                $reviewMeeting,
                $organizationId
            );
        }

        return "Macroplanejamento {$macroPlan->id} criado; Reunião de Revisão Interna agendada para {$reviewMeeting->scheduled_at->format('d/m/Y H:i')}.";
    }

    private function sendNotification(array $config, mixed $entity, array $context): string
    {
        $to = $config['to'] ?? 'creator';

        $recipients = $this->resolveRecipients($to, $config, $entity);
        if ($recipients->isEmpty()) {
            return "Nenhum destinatário resolvido pra '{$to}' — notificação não criada.";
        }

        $message = $this->interpolate($config['message'] ?? 'Automação executada.', $context);
        $title   = $context['task_title'] ?? $context['campaign_name'] ?? $context['project_name'] ?? $this->automation->name;
        $link    = $this->resolveLink($entity);
        $kind    = $config['kind'] ?? 'automation';

        $organizationId = $entity instanceof \Illuminate\Database\Eloquent\Model
            ? ($entity->organization_id ?? $this->automation->createdBy?->organizations()->value('organizations.id'))
            : $this->automation->createdBy?->organizations()->value('organizations.id');

        app(NotificationService::class)->notifyUsers(
            $recipients,
            $kind,
            $title,
            $message,
            $link,
            $entity instanceof \Illuminate\Database\Eloquent\Model ? $entity : null,
            $organizationId
        );

        return "Notificação criada pra " . $recipients->count() . " usuário(s) ('{$to}'): {$message}";
    }

    /**
     * @return Collection<int, \App\Models\User>
     */
    private function resolveRecipients(string $to, array $config, mixed $entity): Collection
    {
        if ($to === 'sector') {
            $sector = !empty($config['sector_id']) ? Sector::find($config['sector_id']) : null;
            return $sector ? $sector->users : collect();
        }

        if ($to === 'role') {
            $role = $config['role'] ?? null;
            if (!$role) {
                return collect();
            }
            $functionalRole = FunctionalRole::where('key', $role)->first();
            return $functionalRole ? $functionalRole->users : collect();
        }

        if (!$entity instanceof Task) {
            // Executor/criador/envolvidos só existem pra Tarefa hoje — outras
            // entidades (Projeto/Campanha) só suportam notificação por setor.
            return collect();
        }

        return match ($to) {
            // creator() pode devolver um Contact (chamado aberto por alguém de
            // fora) — só faz sentido notificar internamente quando for User.
            'executor' => collect([$entity->executor])->filter(),
            'creator'  => collect([$entity->creator()])->filter(fn ($c) => $c instanceof \App\Models\User),
            'all'      => $entity->executors->concat($entity->responsibles)->concat($entity->observers),
            default    => collect(),
        };
    }

    private function resolveLink(mixed $entity): ?string
    {
        return match (true) {
            $entity instanceof Task        => route('tasks.show', $entity),
            $entity instanceof Opportunity => route('opportunities.show', $entity),
            $entity instanceof Meeting     => route('meetings.show', $entity),
            default                         => null,
        };
    }

    private function interpolate(string $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }

    private function resolveEntity(): mixed
    {
        return match ($this->entityType) {
            'task', 'ticket' => Task::find($this->entityId),
            'project'        => Project::find($this->entityId),
            'campaign'       => AdCampaign::find($this->entityId),
            'opportunity'    => Opportunity::find($this->entityId),
            'meeting'        => Meeting::find($this->entityId),
            default          => null,
        };
    }
}

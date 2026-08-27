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
use App\Models\MeetingAttachment;
use App\Models\Opportunity;
use App\Models\FunctionalRole;
use App\Models\Sector;
use App\Models\Sprint;
use App\Models\User;
use App\Services\AiService;
use App\Services\ContextResolver;
use App\Services\NotificationService;
use App\Services\TaskExecutorSync;
use App\Support\BusinessTime;
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
            'create_internal_review_pauta' => $this->createInternalReviewPauta($config, $entity),
            'create_macroplan_from_meeting' => $this->createMacroplanFromMeeting($config, $entity),
            'structure_ata' => $this->structureAta($config, $entity),
            'adjust_date'   => $this->adjustDate($config, $entity),
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
            'situation'   => $config['situation'] ?? null,
            'client_id'   => $clientId,
            'is_ticket'   => $isTicket,
            'origin'      => 'automation',
            'status'      => 'backlog',
            'sprint_id'   => $this->resolveSprintId($config, $organizationId),
            'due_date'    => $this->resolveDueDate($config, $entity),
            'created_by'  => $this->automation->created_by,
        ]);

        $assigneeNote = $this->assignCreatedTask($task, $config, $entity);

        return "Registro criado: {$task->id} ({$task->title}).{$assigneeNote}";
    }

    // Resolve o usuário a ser atribuído (Responsável + Executor) a partir de
    // action_config.assignee_source — 'fixed_user' usa action_config.user_id direto,
    // 'trigger_organizer' só resolve quando a entidade que disparou é uma Reunião
    // ($entity->organized_by). Sem fonte configurada, não atribui ninguém (comportamento
    // igual ao de antes dessa config existir).
    private function resolveAssigneeUserId(array $config, mixed $entity): ?string
    {
        return match ($config['assignee_source'] ?? 'none') {
            'fixed_user'        => $config['user_id'] ?? null,
            'trigger_organizer' => $entity instanceof Meeting ? $entity->organized_by : null,
            default              => null,
        };
    }

    // Atribui a mesma pessoa como Responsável E Executor da tarefa recém-criada, via
    // TaskExecutorSync::sync() — mesmo helper usado por TaskController::store(), garante
    // que a coluna legada tasks.executor_id também seja preenchida.
    private function assignCreatedTask(Task $task, array $config, mixed $entity): string
    {
        $userId = $this->resolveAssigneeUserId($config, $entity);
        if (!$userId) {
            return '';
        }

        TaskExecutorSync::sync($task, [
            'executor_ids'   => [$userId],
            'executor_roles' => [$userId => 'executor'],
            'responsavel_id' => $userId,
        ]);

        return ' Responsável/Executor atribuído automaticamente.';
    }

    private function resolveSprintId(array $config, ?string $organizationId): ?string
    {
        if (($config['sprint_target'] ?? 'none') !== 'active_sprint') {
            return null;
        }

        return Sprint::where('organization_id', $organizationId)
            ->where('status', 'active')
            ->latest('starts_at')
            ->value('id');
    }

    // Base da data de vencimento: 'now' (padrão, comportamento antigo) ou 'entity_field'
    // (lê um campo de data da própria entidade que disparou, ex: scheduled_at da Reunião,
    // pra prazos relativos ao evento — "dia seguinte à reunião" — em vez de relativos a
    // "agora"). due_skip_weekends rola a data resultante pro próximo dia útil quando cai
    // em fim de semana.
    private function resolveDueDate(array $config, mixed $entity): ?\Carbon\CarbonInterface
    {
        $days         = (int) ($config['due_in_days'] ?? 0);
        $skipWeekends = !empty($config['due_skip_weekends']);

        if (($config['due_base'] ?? 'now') === 'entity_field') {
            $field = $config['due_base_field'] ?? null;
            $base  = $field ? ($entity->{$field} ?? null) : null;

            if (!$base instanceof \Carbon\CarbonInterface) {
                return null;
            }

            $date = $base->copy()->addDays($days);
            return $skipWeekends ? BusinessTime::nextBusinessDay($date) : $date;
        }

        // due_base=now: só computa vencimento se algo foi de fato configurado — sem
        // due_in_days nem due_skip_weekends, mantém o comportamento antigo (sem prazo).
        if (empty($config['due_in_days']) && !$skipWeekends) {
            return null;
        }

        $date = now()->addDays($days);
        return $skipWeekends ? BusinessTime::nextBusinessDay($date) : $date;
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
        $reviewDate = BusinessTime::nextBusinessDay(now()->addDay());
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

        // Copia os anexos da reunião de planejamento pra revisão interna (mesmo arquivo no
        // R2, só uma referência nova) — não move nem duplica o arquivo, só o link, pra quem
        // for revisar já ter o material da reunião original à mão sem precisar ir atrás dela.
        $entity->loadMissing('attachments');
        foreach ($entity->attachments as $attachment) {
            MeetingAttachment::create([
                'meeting_id'  => $reviewMeeting->id,
                'filename'    => $attachment->filename,
                'disk_path'   => $attachment->disk_path,
                'disk'        => $attachment->disk,
                'mime_type'   => $attachment->mime_type,
                'size'        => $attachment->size,
                'uploaded_by' => $attachment->uploaded_by,
            ]);
        }

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

        $attachmentsNote = $entity->attachments->isNotEmpty()
            ? " ({$entity->attachments->count()} anexo(s) copiado(s) da reunião original)"
            : '';

        return "Macroplanejamento {$macroPlan->id} criado; Reunião de Revisão Interna agendada para {$reviewMeeting->scheduled_at->format('d/m/Y H:i')}{$attachmentsNote}.";
    }

    // Gera a pauta da Reunião Interna via IA a partir da ATA da reunião com o cliente
    // (kickoff ou macroplanejamento recorrente) e já cria a Reunião Interna com essa pauta
    // preenchida. Substitui o antigo create_macroplan_review nesse ponto do fluxo — o Macro
    // em si passou a nascer manualmente, depois, a partir das duas ATAs (decisão do
    // usuário); esta ação só cuida do elo Reunião cliente → Reunião Interna.
    private function createInternalReviewPauta(array $config, mixed $entity): string
    {
        if (!$entity instanceof Meeting) {
            throw new \RuntimeException('create_internal_review_pauta só funciona com entidade Reunião.');
        }

        // Idempotente: se já existe uma Reunião Interna gerada a partir desta, não recria
        // (evita duplicar se o status da reunião original oscilar pra trás e pra frente).
        $alreadyExists = Meeting::where('source_meeting_id', $entity->id)->exists();
        if ($alreadyExists) {
            return "Reunião {$entity->id} já tem uma Reunião Interna gerada — nada feito.";
        }

        if (empty($entity->ata)) {
            throw new \RuntimeException('Reunião sem ATA preenchida — não é possível gerar a pauta da Reunião Interna.');
        }

        if (!$entity->client_id) {
            throw new \RuntimeException('Reunião sem cliente vinculado — não é possível gerar a Reunião Interna.');
        }

        $agentId = $config['agent_id'] ?? throw new \RuntimeException('create_internal_review_pauta sem agente de IA configurado.');
        $agent = AiAgent::findOrFail($agentId);

        $entity->loadMissing('client', 'attachments');

        $organizationId = $entity->organization_id ?? $this->automation->createdBy?->organizations()->value('organizations.id');

        $userMessage = "Tipo da reunião: {$entity->typeLabel()}\n"
            . "Cliente: {$entity->client->displayName()}\n"
            . "Data da reunião: {$entity->scheduled_at->format('d/m/Y')}\n\n"
            . "ATA:\n{$entity->ata}\n\n"
            . "Gere a pauta da Reunião Interna seguindo a estrutura definida.";

        $pauta = app(\App\Services\AiService::class)->run(
            agent: $agent,
            userMessage: $userMessage,
            userId: $this->automation->created_by,
            clientId: $entity->client_id,
            trigger: 'automation:create_internal_review_pauta',
        );

        // Próximo dia útil (pula sábado/domingo), no mesmo horário da reunião original.
        $reviewDate = BusinessTime::nextBusinessDay(now()->addDay());
        $reviewDate->setTimeFromTimeString($entity->scheduled_at->format('H:i:s'));

        $reviewMeeting = Meeting::create([
            'organization_id'   => $organizationId,
            'title'             => 'Revisão Interna — ' . $entity->client->displayName(),
            'type'              => 'revisao_interna',
            'modality'          => $entity->modality,
            'status'            => 'agendada',
            'client_id'         => $entity->client_id,
            'source_meeting_id' => $entity->id,
            'organized_by'      => $entity->organized_by,
            'created_by'        => $this->automation->created_by,
            'scheduled_at'      => $reviewDate,
            'duration_minutes'  => $entity->duration_minutes,
            'agenda'            => $pauta,
        ]);

        // Copia os anexos complementares da reunião com cliente (documentos, materiais) —
        // mesmo arquivo no R2, só uma referência nova, não move nem duplica.
        foreach ($entity->attachments as $attachment) {
            MeetingAttachment::create([
                'meeting_id'  => $reviewMeeting->id,
                'filename'    => $attachment->filename,
                'disk_path'   => $attachment->disk_path,
                'disk'        => $attachment->disk,
                'mime_type'   => $attachment->mime_type,
                'size'        => $attachment->size,
                'uploaded_by' => $attachment->uploaded_by,
            ]);
        }

        $role = !empty($config['role']) ? FunctionalRole::where('key', $config['role'])->first() : null;
        $recipients = $role ? $role->users : collect();

        if ($recipients->isNotEmpty()) {
            $reviewMeeting->participants()->sync($recipients->pluck('id'));

            app(NotificationService::class)->notifyUsers(
                $recipients,
                'automation',
                'Reunião Interna com pauta gerada — ' . $entity->client->displayName(),
                'Pauta gerada por IA a partir da ATA. Reunião marcada para ' . $reviewMeeting->scheduled_at->format('d/m/Y H:i') . '.',
                route('meetings.show', $reviewMeeting),
                $reviewMeeting,
                $organizationId
            );
        }

        return "Reunião Interna {$reviewMeeting->id} criada para {$reviewMeeting->scheduled_at->format('d/m/Y H:i')} com pauta gerada por IA ({$agent->name}).";
    }

    // Cria o Macroplanejamento a partir de uma Reunião (Macro/Kickoff) realizada, linka a
    // reunião a ele, e cria a Tarefa de checklist "Criar macroplanejamento" já vinculada
    // (macro_plan_id), atribuída ao organizador da reunião. Ação específica (mesmo
    // princípio de create_macroplan_review/create_internal_review_pauta): encadeia 2
    // registros diferentes, um referenciando o outro, o que não cabe num action_config
    // genérico sem um motor de ações em sequência — o gatilho (status/tipo de reunião,
    // via condições) continua 100% configurável pela tela, só o conteúdo da ação é fixo.
    // Não cria mais nenhuma segunda Reunião (isso passou a ser resolvido via mudança de
    // status na própria reunião do cliente, ver Meeting::$statuses['revisao_ata']).
    private function createMacroplanFromMeeting(array $config, mixed $entity): string
    {
        if (!$entity instanceof Meeting) {
            throw new \RuntimeException('create_macroplan_from_meeting só funciona com entidade Reunião.');
        }

        // Idempotente: se o status oscilar de novo pra "realizada", não recria um
        // segundo Macro/Tarefa pra mesma reunião original.
        if ($entity->macro_plan_id) {
            return "Reunião já vinculada ao Macroplanejamento {$entity->macro_plan_id} — nada feito.";
        }

        if (!$entity->client_id) {
            throw new \RuntimeException('Reunião sem cliente vinculado — não é possível criar o Macroplanejamento.');
        }

        $entity->loadMissing('client');

        $organizationId = $entity->organization_id ?? $this->automation->createdBy?->organizations()->value('organizations.id');

        $role = !empty($config['responsible_role']) ? FunctionalRole::where('key', $config['responsible_role'])->first() : null;
        // MacroPlan.responsible_id é FK única — um Papel Funcional pode ter mais de uma
        // pessoa, então fica o primeiro usuário do papel (simplificação assumida).
        $responsibleId = $role?->users->first()?->id;

        $macroPlan = MacroPlan::create([
            'organization_id' => $organizationId,
            'client_id'       => $entity->client_id,
            'responsible_id'  => $responsibleId,
            'created_by'      => $this->automation->created_by,
            'title'           => $entity->client->displayName() . ' — Planejamento ' . now()->format('m/Y'),
            'period_start'    => now(),
            'period_end'      => now()->addDays(89),
            'status'          => 'em_planejamento',
        ]);

        $entity->update(['macro_plan_id' => $macroPlan->id]);

        $task = Task::create([
            'organization_id' => $organizationId,
            'macro_plan_id'   => $macroPlan->id,
            'meeting_id'      => $entity->id,
            'client_id'       => $entity->client_id,
            'title'           => $this->interpolate($config['task_title'] ?? 'Criar macroplanejamento', ['client_name' => $entity->client->displayName()]),
            'description'     => 'Gerado automaticamente a partir da Reunião "' . $entity->title . '".',
            'task_type'       => 'estrategia',
            'origin'          => 'automation',
            'status'          => 'backlog',
            'due_date'        => BusinessTime::nextBusinessDay(now()->addDay()),
            'created_by'      => $this->automation->created_by,
        ]);

        if ($entity->organized_by) {
            TaskExecutorSync::sync($task, [
                'executor_ids'   => [$entity->organized_by],
                'executor_roles' => [$entity->organized_by => 'executor'],
                'responsavel_id' => $entity->organized_by,
            ]);
        }

        return "Macroplanejamento {$macroPlan->id} criado; Tarefa \"{$task->title}\" ({$task->id}) vinculada, prazo {$task->due_date->format('d/m/Y')}.";
    }

    // Gera (ou atualiza) a ATA via IA a partir da TRANSCRIÇÃO bruta da call (campo
    // "transcricao" — colada manualmente, ex: saída de gravação/Whisper). Quando a
    // reunião já tem uma ATA de uma rodada anterior (gerada por IA ou escrita à mão), ela
    // entra no prompt como contexto pra fusão — o agente deve atualizar/enriquecer, não
    // substituir do zero (suporta reunião que recebe uma segunda transcrição depois, ex:
    // continuação). Não notifica ninguém — isso fica por conta de uma automação separada
    // (send_notification, destinatário "participants"). Substitui create_internal_review_pauta
    // pra esse ponto do fluxo — não cria mais nenhuma Reunião Interna.
    private function structureAta(array $config, mixed $entity): string
    {
        if (!$entity instanceof Meeting) {
            throw new \RuntimeException('structure_ata só funciona com entidade Reunião.');
        }

        if (empty($entity->transcricao)) {
            throw new \RuntimeException('Reunião sem Transcrição preenchida — não é possível gerar a ATA.');
        }

        $agentId = $config['agent_id'] ?? throw new \RuntimeException('structure_ata sem agente de IA configurado.');
        $agent = AiAgent::findOrFail($agentId);

        $entity->loadMissing('client');

        $userMessage = "Tipo da reunião: {$entity->typeLabel()}\n"
            . 'Cliente: ' . ($entity->client?->displayName() ?? '—') . "\n"
            . "Data da reunião: {$entity->scheduled_at->format('d/m/Y')}\n\n";

        if (!empty($entity->ata)) {
            $userMessage .= "ATA JÁ EXISTENTE (atualize e enriqueça, não substitua do zero):\n{$entity->ata}\n\n";
        }

        $userMessage .= "TRANSCRIÇÃO BRUTA (nova):\n{$entity->transcricao}\n\n"
            . 'Gere a ATA seguindo o formato definido.';

        $response = app(\App\Services\AiService::class)->run(
            agent: $agent,
            userMessage: $userMessage,
            userId: $this->automation->created_by,
            clientId: $entity->client_id,
            trigger: 'automation:structure_ata',
        );

        // O agente pode devolver só a ATA (agentes antigos que não conhecem o marcador)
        // ou ATA + Pauta de Revisão Interna separadas pelo marcador abaixo — contrato
        // definido no system_prompt do agente, não aqui (ver AiAgent "ATA Estruturada —
        // Reunião com Cliente"). A pauta usa a mesma sintaxe de checklist da ATA
        // ("- [ ]"/"- [x]") pra marcar o que já foi identificado e o que ficou pendente.
        [$ata, $agenda] = self::splitAtaAndAgenda($response);

        $update = [
            'ata' => $ata,
            'ata_recorded_at' => $entity->ata_recorded_at ?? now(),
        ];
        if ($agenda !== null) {
            $update['agenda'] = $agenda;
        }

        $entity->update($update);

        return $agenda !== null
            ? "ATA e Pauta de Revisão Interna geradas e salvas ({$agent->name})."
            : "ATA gerada e salva ({$agent->name}).";
    }

    private const PAUTA_MARKER = '===PAUTA_REVISAO_INTERNA===';

    /**
     * @return array{0: string, 1: ?string}
     */
    private static function splitAtaAndAgenda(string $response): array
    {
        $pos = mb_strpos($response, self::PAUTA_MARKER);
        if ($pos === false) {
            return [trim($response), null];
        }

        $ata = trim(mb_substr($response, 0, $pos));
        $agenda = trim(mb_substr($response, $pos + mb_strlen(self::PAUTA_MARKER)));

        return [$ata, $agenda !== '' ? $agenda : null];
    }

    // Ajusta um campo de data na própria entidade por N dias (positivo ou negativo) a
    // partir de uma base configurável — date_base=field (padrão) desloca o valor ATUAL do
    // próprio campo (ex: tarefa atrasada tem o prazo empurrado); date_base=trigger calcula
    // a partir de AGORA, o momento em que a automação disparou, ignorando o valor anterior
    // do campo (ex: reunião reagendada — a nova Data/Hora não depende de qual era a data
    // antiga, e sim de quando a mudança de status aconteceu). skip_weekends rola o
    // resultado pro próximo dia útil (mesmo BusinessTime::nextBusinessDay usado em
    // create_record/due_skip_weekends).
    private function adjustDate(array $config, mixed $entity): string
    {
        $field = $config['date_field'] ?? throw new \RuntimeException('adjust_date sem campo de data configurado.');

        if (($config['date_base'] ?? 'field') === 'trigger') {
            $base = now();
        } else {
            $base = $entity->{$field} ?? null;
            if (!$base instanceof \Carbon\CarbonInterface) {
                throw new \RuntimeException("Campo '{$field}' sem data preenchida — não é possível ajustar a partir dele.");
            }
        }

        $offsetDays = (int) ($config['offset_days'] ?? 0);
        $skipWeekends = !empty($config['skip_weekends']);

        $new = $base->copy()->addDays($offsetDays);
        if ($skipWeekends) {
            $new = BusinessTime::nextBusinessDay($new);
        }

        $entity->update([$field => $new]);

        return "Campo '{$field}' ajustado (base {$base->format('d/m/Y H:i')}) para {$new->format('d/m/Y H:i')}.";
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

        if ($to === 'participants') {
            return $entity instanceof Meeting ? $entity->participants : collect();
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
            $entity instanceof MacroPlan   => route('macroplans.edit', $entity),
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
            'macro_plan'     => MacroPlan::find($this->entityId),
            default          => null,
        };
    }
}

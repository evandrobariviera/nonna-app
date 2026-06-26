<?php

namespace App\Jobs;

use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Task;
use App\Models\Project;
use App\Models\AdCampaign;
use App\Models\AiAgent;
use App\Services\AiService;
use App\Services\ContextResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
                'duration_ms'    => now()->diffInMilliseconds($startedAt),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms'   => now()->diffInMilliseconds($startedAt),
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
            'send_notification' => $this->sendNotification($config, $entity),
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

    private function sendNotification(array $config, mixed $entity): string
    {
        // Implementação futura: integrar com sistema de notificações
        $to      = $config['to'] ?? 'creator';
        $message = $config['message'] ?? 'Automação executada.';

        Log::info("Notificação via automação [{$this->automation->name}]: {$message}", [
            'to'        => $to,
            'entity_id' => $this->entityId,
        ]);

        return "Notificação enviada para '{$to}': {$message}";
    }

    private function resolveEntity(): mixed
    {
        return match ($this->entityType) {
            'task'     => Task::find($this->entityId),
            'project'  => Project::find($this->entityId),
            'campaign' => AdCampaign::find($this->entityId),
            default    => null,
        };
    }
}

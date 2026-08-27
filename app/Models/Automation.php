<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'name', 'description',
        'entity_type',
        'trigger_type', 'trigger_config',
        'action_type', 'action_config',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'action_config'  => 'array',
        'is_active'      => 'boolean',
    ];

    // ── Definições de tipos ────────────────────────────────────────────────

    public static array $entityTypes = [
        'task'        => 'Tarefa',
        'ticket'      => 'Ticket',
        'project'     => 'Projeto',
        'campaign'    => 'Campanha',
        'opportunity' => 'Oportunidade',
        'meeting'     => 'Reunião',
        'macro_plan'  => 'Planejamento',
    ];

    public static array $triggerTypes = [
        'status_changed' => 'Status mudou',
        'field_updated'  => 'Campo atualizado',
        'date_reached'   => 'Data alcançada',
        'executor_added' => 'Responsável/Executor adicionado',
        'created'        => 'Criado',
        'manual'         => 'Manual (botão)',
    ];

    public static array $actionTypes = [
        'run_ai_agent'      => 'Rodar Agente de IA',
        'send_webhook'      => 'Enviar Webhook',
        'update_field'      => 'Atualizar Campo',
        'send_notification'       => 'Enviar Notificação',
        'create_record'           => 'Criar Tarefa/Ticket',
        'create_macroplan_review' => 'Criar Macroplanejamento + Reunião de Revisão Interna',
        'create_internal_review_pauta' => 'Gerar Pauta (IA) + Criar Reunião Interna',
        'create_macroplan_from_meeting' => 'Criar Macroplanejamento + Tarefa a partir da Reunião',
        'structure_ata'    => 'Estruturar ATA (IA)',
        'adjust_date'      => 'Ajustar Data',
    ];

    /**
     * Campos de data disponíveis pro gatilho "Data alcançada" E pra ação "Ajustar Data",
     * por entity_type — cada entidade tem seus próprios campos de data (Tarefa:
     * vencimento/aprovação/publicação; Planejamento: fim do ciclo; Reunião: data/hora).
     * Substitui o antigo $dateFields fixo (só Tarefa); o formato salvo em
     * trigger_config.date_field/action_config.date_field não muda, só a lista de opções
     * na UI passa a variar por entidade — mesmo padrão de conditionFieldsFor().
     */
    public static function dateFieldsFor(string $entityType): array
    {
        return match ($entityType) {
            'task', 'ticket' => [
                'due_date'      => 'Vencimento',
                'approval_date' => 'Data de Aprovação',
                'publish_date'  => 'Data de Publicação',
            ],
            'macro_plan' => [
                'period_end' => 'Fim do Ciclo',
            ],
            'meeting' => [
                'scheduled_at' => 'Data/Hora da Reunião',
            ],
            default => [],
        };
    }

    /**
     * Campos disponíveis pra montar condições (bloco "SE") por entity_type — usado tanto no
     * builder de condições da UI quanto por conditionsMatch(). Substitui o antigo
     * $entityFields (nunca chegou a ser usado de verdade — só tinha referências em string
     * pros arrays de valores, não os arrays em si).
     */
    public static function conditionFieldsFor(string $entityType): array
    {
        // Ticket é a mesma tabela de Tarefa (Task.is_ticket=true) — mesmos campos de condição,
        // sem repetir a lista (o filtro "é ticket?" não existe mais aqui porque a entidade em
        // si já decide isso).
        if ($entityType === 'ticket') {
            return self::conditionFieldsFor('task');
        }

        if ($entityType === 'task') {
            return [
                'status'      => ['label' => 'Status',      'options' => self::labelMap(Task::$statuses)],
                'situation'   => ['label' => 'Situação',     'options' => Task::$situations],
                'task_type'   => ['label' => 'Tipo',         'options' => Task::$types],
                'destination' => ['label' => 'Destino',      'options' => Task::$destinations],
                'origin'      => ['label' => 'Origem',       'options' => Task::$origins],
                'priority'    => ['label' => 'Prioridade',   'options' => self::labelMap(Task::$priorities)],
                'role'        => ['label' => 'Papel (Responsável/Executor)', 'options' => [
                    'executor'    => 'Executor',
                    'responsavel' => 'Responsável',
                    'observador'  => 'Observador',
                ]],
                // Só faz sentido combinado com o gatilho "Responsável/Executor adicionado"
                // (changeData carrega user_id — ver TaskExecutorSync::sync()) — filtra por
                // uma pessoa específica em vez de só o papel, ex: "atribuído ao Fulano".
                'user_id'     => ['label' => 'Responsável/Executor específico', 'options' => self::userOptions()],
            ];
        }

        if ($entityType === 'project') {
            return [
                'status' => ['label' => 'Status', 'options' => self::labelMap(Project::$statuses)],
                'type'   => ['label' => 'Tipo',   'options' => self::labelMap(Project::$types)],
            ];
        }

        if ($entityType === 'campaign') {
            return [
                'management_status'    => ['label' => 'Status de Gestão',   'options' => self::labelMap(AdCampaign::$managementStatuses)],
                'management_situation' => ['label' => 'Situação',           'options' => AdCampaign::$managementSituations],
                'optimization_tier'    => ['label' => 'Tier de Otimização', 'options' => self::labelMap(AdCampaign::$optimizationTiers)],
            ];
        }

        if ($entityType === 'opportunity') {
            return [
                'stage' => ['label' => 'Estágio', 'options' => self::labelMap(Opportunity::$stages)],
            ];
        }

        if ($entityType === 'meeting') {
            return [
                'status' => ['label' => 'Status', 'options' => self::labelMap(Meeting::$statuses)],
                'type'   => ['label' => 'Tipo',   'options' => Meeting::$types],
            ];
        }

        if ($entityType === 'macro_plan') {
            return [
                'status' => ['label' => 'Status', 'options' => self::labelMap(MacroPlan::$statuses)],
            ];
        }

        return [
            'status' => ['label' => 'Status', 'options' => []],
        ];
    }

    private static function labelMap(array $withLabels): array
    {
        return collect($withLabels)->map(fn ($meta) => $meta['label'] ?? $meta)->all();
    }

    // Lista de usuários pra montar o dropdown da condição "Responsável/Executor
    // específico" — escopada à organização atual (User não é Tenantable, é ligado por
    // pivot organization_users, então o filtro precisa ser explícito aqui).
    private static function userOptions(): array
    {
        if (!app()->has('currentOrganization')) {
            return [];
        }

        return User::whereHas('organizations', fn ($q) => $q->where('organizations.id', app('currentOrganization')->id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class)->orderByDesc('ran_at');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function entityTypeLabel(): string
    {
        return self::$entityTypes[$this->entity_type] ?? $this->entity_type;
    }

    public function triggerTypeLabel(): string
    {
        return self::$triggerTypes[$this->trigger_type] ?? $this->trigger_type;
    }

    public function actionTypeLabel(): string
    {
        return self::$actionTypes[$this->action_type] ?? $this->action_type;
    }

    public function triggerSummary(): string
    {
        $config = $this->trigger_config ?? [];
        $fields = self::conditionFieldsFor($this->entity_type);

        $base = match ($this->trigger_type) {
            'status_changed' => sprintf(
                'Status "%s" → "%s"',
                $config['from'] ?? '*',
                $config['to'] ?? '*'
            ),
            'field_updated'  => sprintf(
                'Campo "%s": "%s" → "%s"',
                $fields[$config['field'] ?? '']['label'] ?? ($config['field'] ?? '?'),
                $config['from'] ?? '*',
                $config['to'] ?? '*'
            ),
            'date_reached'   => $this->dateReachedSummary($config),
            'executor_added' => 'Responsável/Executor adicionado',
            'created'        => 'Ao ser criado',
            'manual'         => 'Acionado manualmente',
            default          => $this->trigger_type,
        };

        $conditionCount = count($config['conditions'] ?? []);
        if ($conditionCount > 0) {
            $logic = strtoupper($config['conditions_logic'] ?? 'and') === 'OR' ? 'OU' : 'E';
            $base .= " + {$conditionCount} condição(ões) em {$logic}";
        }

        return $base;
    }

    private function dateReachedSummary(array $config): string
    {
        $label = self::dateFieldsFor($this->entity_type)[$config['date_field'] ?? ''] ?? '?';
        $offsetDays = (int) ($config['offset_days'] ?? 0);

        if ($offsetDays > 0) {
            return "Quando faltam {$offsetDays} dia(s) pra \"{$label}\"";
        }

        return 'Quando "' . $label . '" chega';
    }

    public function actionSummary(): string
    {
        $config = $this->action_config ?? [];
        $fields = self::conditionFieldsFor($this->entity_type);

        return match ($this->action_type) {
            'run_ai_agent'      => 'Agente: ' . ($config['agent_name'] ?? $config['agent_id'] ?? '?'),
            'send_webhook'      => 'POST → ' . ($config['url'] ?? '?'),
            'update_field'      => 'Campo "' . ($fields[$config['field'] ?? '']['label'] ?? ($config['field'] ?? '?')) . '" = "' . ($config['value'] ?? '?') . '"',
            'send_notification' => 'Notificar ' . ($config['to'] ?? '?'),
            'create_record'     => 'Criar ' . (($config['record_type'] ?? 'ticket') === 'task' ? 'Tarefa' : 'Ticket'),
            'create_macroplan_review' => 'Cria Macroplanejamento + Reunião de Revisão Interna',
            'create_internal_review_pauta' => 'Agente: ' . ($config['agent_name'] ?? $config['agent_id'] ?? '?') . ' gera pauta + cria Reunião Interna',
            'create_macroplan_from_meeting' => 'Cria Macroplanejamento + Tarefa vinculada',
            'structure_ata' => 'Estrutura ATA (IA)',
            'adjust_date'   => $this->adjustDateSummary($config),
            default             => $this->action_type,
        };
    }

    private function adjustDateSummary(array $config): string
    {
        $label = self::dateFieldsFor($this->entity_type)[$config['date_field'] ?? ''] ?? '?';
        $offset = (int) ($config['offset_days'] ?? 0);
        $sign = $offset >= 0 ? '+' : '';

        return "Ajusta \"{$label}\" em {$sign}{$offset} dia(s)";
    }

    // Verifica se esta automação deve disparar dado um gatilho e dados de mudança. $entity é
    // opcional — usado tanto pelo from/to quanto pelas condições extras (conditionsMatch()),
    // que olham o estado atual da entidade, não só o changeData do próprio evento.
    public function matches(string $triggerType, array $changeData, ?Model $entity = null): bool
    {
        if ($this->trigger_type !== $triggerType) {
            return false;
        }

        $config = $this->trigger_config ?? [];

        if ($triggerType === 'status_changed') {
            $fromMatch = empty($config['from']) || $config['from'] === '*' || $config['from'] === ($changeData['from'] ?? null);
            $toMatch   = empty($config['to'])   || $config['to']   === '*' || $config['to']   === ($changeData['to'] ?? null);

            return $fromMatch && $toMatch && $this->conditionsMatch($entity, $changeData);
        }

        if ($triggerType === 'field_updated') {
            if (($config['field'] ?? null) !== ($changeData['field'] ?? null)) {
                return false;
            }

            $fromMatch = empty($config['from']) || $config['from'] === '*' || $config['from'] === ($changeData['from'] ?? null);
            $toMatch   = empty($config['to'])   || $config['to']   === '*' || $config['to']   === ($changeData['to'] ?? null);

            return $fromMatch && $toMatch && $this->conditionsMatch($entity, $changeData);
        }

        if (in_array($triggerType, ['date_reached', 'executor_added'], true)) {
            return $this->conditionsMatch($entity, $changeData);
        }

        return true;
    }

    /**
     * Avalia a lista de condições extras (trigger_config.conditions, combinadas por
     * trigger_config.conditions_logic = 'and'|'or') contra o changeData do evento (prioridade)
     * ou o estado atual da entidade. Sem condições configuradas, sempre bate (comportamento
     * anterior, quando só existia o filtro fixo de `destination`).
     */
    public function conditionsMatch(?Model $entity, array $changeData = []): bool
    {
        $conditions = $this->trigger_config['conditions'] ?? [];
        if (empty($conditions)) {
            return true;
        }

        $logic = $this->trigger_config['conditions_logic'] ?? 'and';

        $results = array_map(function (array $condition) use ($entity, $changeData) {
            $field = $condition['field'] ?? null;
            if (!$field) {
                return true;
            }

            $actual = array_key_exists($field, $changeData) ? $changeData[$field] : $entity?->{$field};
            if (is_bool($actual)) {
                $actual = $actual ? '1' : '0';
            }

            $matches = (string) $actual === (string) ($condition['value'] ?? '');

            return ($condition['operator'] ?? '=') === '!=' ? !$matches : $matches;
        }, $conditions);

        return $logic === 'or' ? in_array(true, $results, true) : !in_array(false, $results, true);
    }
}

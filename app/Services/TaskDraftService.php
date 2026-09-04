<?php

namespace App\Services;

use App\Models\FunctionalRole;
use App\Models\Project;
use App\Models\ProjectPlaybook;
use App\Models\ProjectPlaybookTask;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ponto único de criação de Tarefas a partir do Assistente de Lançamento de
 * Tarefas — tanto pelo caminho determinístico (aplicar um ProjectPlaybook)
 * quanto pelo caminho conversacional (confirmar rascunhos gerados pela IA
 * em ProjectTaskAssistantController). Os dois compartilham createTask(),
 * que resolve client_id/macro_plan_id herdados do Projeto, due_date
 * (absoluta ou relativa ao início do projeto) e responsável (a partir de um
 * Papel Funcional, quando informado).
 *
 * TaskController (store/storeStandalone/storeFromMeeting) não foi migrado
 * pra usar este service — evita mexer num fluxo que já funciona — mas o
 * shape de $data aceito por createTask() é compatível com o que
 * TaskController::storeRules() já produz, então a porta fica aberta pra
 * unificação futura.
 */
class TaskDraftService
{
    /**
     * Aplica um Playbook a um Projeto: cria, de uma vez, exatamente as tarefas
     * cadastradas no Playbook (sem IA — determinístico). Marca o Projeto como
     * originado deste Playbook (rastreabilidade — ver Project::playbook() —
     * e também trava de reaplicação, ver abaixo).
     *
     * Bloqueia reaplicar o MESMO Playbook no mesmo Projeto (pedido explícito
     * do usuário, pra evitar tarefa duplicada por duplo clique) — comparando
     * com projects.project_playbook_id. Só detecta o último Playbook
     * aplicado (o campo guarda 1 valor só); aplicar A, depois B, depois A de
     * novo não é pego por essa checagem — limitação aceita, cobre o caso comum.
     *
     * @return array{tasks: Collection<int, Task>, warnings: string[], already_applied: bool}
     */
    public function applyPlaybook(Project $project, ProjectPlaybook $playbook, ?int $userId = null): array
    {
        if ($project->project_playbook_id === $playbook->id) {
            return ['tasks' => collect(), 'warnings' => [], 'already_applied' => true];
        }

        $playbook->loadMissing('tasks');
        $warnings = [];

        $tasks = DB::connection('pgsql')->transaction(function () use ($project, $playbook, $userId, &$warnings) {
            return $playbook->tasks->map(function (ProjectPlaybookTask $item) use ($project, $userId, &$warnings) {
                [$task, $warning] = $this->createTask($project, [
                    'title'              => $item->title,
                    'description'        => $item->description,
                    'task_type'          => $item->task_type,
                    'destination'        => $item->destination,
                    'priority'           => $item->priority,
                    'due_offset_days'    => $item->due_offset_days,
                    'functional_role_id' => $item->functional_role_id,
                ], 'playbook', $userId);

                if ($warning) {
                    $warnings[] = $warning;
                }

                return $task;
            });
        });

        // Atualiza sempre (não só quando vazio) — é o sinal que a checagem de
        // reaplicação no topo do método usa pra reconhecer "este Playbook já
        // foi aplicado aqui" na próxima tentativa.
        if ($tasks->isNotEmpty()) {
            $project->update(['project_playbook_id' => $playbook->id]);
        }

        return ['tasks' => $tasks, 'warnings' => $warnings, 'already_applied' => false];
    }

    /**
     * Cria tarefas reais a partir de rascunhos já revisados/editados pelo
     * usuário no drawer do Assistente (ver ProjectTaskAssistantController::confirmDrafts()).
     * $drafts já deve ter sido validado no controller — este método não
     * revalida, só confia no shape.
     *
     * @param  array<int, array{title: string, description?: ?string, task_type: string, destination?: ?string, priority?: ?string, due_date?: ?string, due_offset_days?: ?int, functional_role_id?: ?string}>  $drafts
     * @return array{tasks: Collection<int, Task>, warnings: string[]}
     */
    public function createFromDrafts(Project $project, array $drafts, ?int $userId = null, string $origin = 'ai_assistant'): array
    {
        $warnings = [];

        $tasks = DB::connection('pgsql')->transaction(function () use ($project, $drafts, $userId, $origin, &$warnings) {
            return collect($drafts)->map(function (array $draft) use ($project, $userId, $origin, &$warnings) {
                [$task, $warning] = $this->createTask($project, $draft, $origin, $userId);

                if ($warning) {
                    $warnings[] = $warning;
                }

                return $task;
            });
        });

        return ['tasks' => $tasks, 'warnings' => $warnings];
    }

    /**
     * Único lugar que resolve client_id/macro_plan_id herdados, due_date e
     * responsável — usado tanto por applyPlaybook() quanto createFromDrafts().
     *
     * @return array{0: Task, 1: ?string} [tarefa criada, aviso opcional]
     */
    private function createTask(Project $project, array $data, string $origin, ?int $userId): array
    {
        [$responsavelId, $warning] = $this->resolveResponsible($data['functional_role_id'] ?? null, $data['title'] ?? '');

        $attributes = [
            'macro_plan_id' => $project->macro_plan_id,
            'client_id'     => $project->client_id,
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'task_type'     => $data['task_type'],
            'destination'   => $data['destination'] ?? null,
            'due_date'      => $this->resolveDueDate($project, $data),
            'status'        => 'backlog',
            'origin'        => $origin,
            'created_by'    => $userId,
        ];

        // tasks.priority é NOT NULL com default 'normal' no banco — passar
        // null explicitamente (em vez de omitir a chave) sobrescreveria esse
        // default e quebraria a constraint. Só inclui quando um valor válido
        // vier no rascunho/playbook.
        if (!empty($data['priority'])) {
            $attributes['priority'] = $data['priority'];
        }

        $task = $project->tasks()->create($attributes);

        if ($responsavelId) {
            TaskExecutorSync::sync($task, ['responsavel_id' => $responsavelId]);
        }

        return [$task, $warning];
    }

    private function resolveDueDate(Project $project, array $data): ?Carbon
    {
        if (!empty($data['due_date'])) {
            return Carbon::parse($data['due_date']);
        }

        if (array_key_exists('due_offset_days', $data) && $data['due_offset_days'] !== null && $data['due_offset_days'] !== '') {
            $base = $project->start_date ? Carbon::parse($project->start_date) : now();
            return $base->copy()->addDays((int) $data['due_offset_days']);
        }

        return null;
    }

    /**
     * Resolve o(s) usuário(s) vinculado(s) a um Papel Funcional pra virar o
     * responsável da tarefa. Sem vínculo direto FunctionalRole↔Task hoje — o
     * único caminho é achar os User(s) do papel e usar o primeiro, mesmo
     * critério já usado em AutomationJob::finalizeMacroMeeting() (0 usuários =
     * sem responsável; 2+ = pega o primeiro arbitrariamente). Ambos os casos
     * geram aviso pro usuário revisar manualmente — ver risco #1 do plano.
     *
     * @return array{0: ?int, 1: ?string} [user_id do responsável, aviso opcional]
     */
    private function resolveResponsible(?string $functionalRoleId, string $taskTitle): array
    {
        if (!$functionalRoleId) {
            return [null, null];
        }

        $role = FunctionalRole::find($functionalRoleId);
        if (!$role) {
            return [null, "Papel funcional não encontrado — tarefa \"{$taskTitle}\" criada sem responsável."];
        }

        $users = $role->users;
        if ($users->isEmpty()) {
            return [null, "Papel \"{$role->name}\" sem usuário vinculado — tarefa \"{$taskTitle}\" ficou sem responsável."];
        }

        if ($users->count() > 1) {
            $first = $users->first();
            return [$first->id, "Papel \"{$role->name}\" tem {$users->count()} usuários vinculados — responsável de \"{$taskTitle}\" definido como {$first->name} (primeiro encontrado). Revise se necessário."];
        }

        return [$users->first()->id, null];
    }
}

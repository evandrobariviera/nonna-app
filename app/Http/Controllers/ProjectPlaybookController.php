<?php

namespace App\Http\Controllers;

use App\Models\FunctionalRole;
use App\Models\Project;
use App\Models\ProjectPlaybook;
use App\Models\Task;
use App\Services\TaskDraftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectPlaybookController extends Controller
{
    public function index()
    {
        $playbooks = ProjectPlaybook::withCount('tasks', 'phases')->orderBy('name')->get();

        return view('playbooks.index', compact('playbooks'));
    }

    public function create()
    {
        return view('playbooks.create', [
            'playbook'       => null,
            'functionalRoles'=> $this->functionalRoles(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $playbook = DB::connection('pgsql')->transaction(function () use ($data, $request) {
            $playbook = ProjectPlaybook::create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'disciplines' => $data['disciplines'] ?? [],
                'is_active'   => $request->boolean('is_active', true),
                'created_by'  => Auth::id(),
            ]);

            $this->syncTasks($playbook, $data['tasks'] ?? [], $data['phases'] ?? []);

            return $playbook;
        });

        return redirect()->route('playbooks.index')->with('success', "Playbook \"{$playbook->name}\" criado.");
    }

    public function edit(ProjectPlaybook $playbook)
    {
        $playbook->load('tasks', 'phases');

        return view('playbooks.edit', [
            'playbook'        => $playbook,
            'functionalRoles' => $this->functionalRoles(),
        ]);
    }

    public function update(Request $request, ProjectPlaybook $playbook)
    {
        $data = $this->validated($request);

        DB::connection('pgsql')->transaction(function () use ($playbook, $data, $request) {
            $playbook->update([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'disciplines' => $data['disciplines'] ?? [],
                'is_active'   => $request->boolean('is_active', true),
            ]);

            $this->syncTasks($playbook, $data['tasks'] ?? [], $data['phases'] ?? []);
        });

        return redirect()->route('playbooks.index')->with('success', "Playbook \"{$playbook->name}\" atualizado.");
    }

    public function destroy(ProjectPlaybook $playbook)
    {
        $name = $playbook->name;
        $playbook->delete(); // cascade cuida de project_playbook_tasks; projetos com project_playbook_id caem pra null

        return redirect()->route('playbooks.index')->with('success', "Playbook \"{$name}\" removido.");
    }

    public function toggleActive(ProjectPlaybook $playbook)
    {
        $playbook->update(['is_active' => !$playbook->is_active]);

        return back()->with('success', $playbook->is_active ? 'Playbook ativado.' : 'Playbook desativado.');
    }

    // Ação determinística — cria de uma vez todas as tarefas do Playbook no
    // Projeto informado. Sem preview/IA no meio, por isso é POST síncrono
    // com redirect, não AJAX (ver TaskDraftService::applyPlaybook()).
    public function apply(Project $project, ProjectPlaybook $playbook, TaskDraftService $service)
    {
        $result = $service->applyPlaybook($project, $playbook, Auth::id());

        if ($result['already_applied']) {
            return back()->with('error', "O Playbook \"{$playbook->name}\" já foi aplicado a este projeto — nada foi criado, pra evitar tarefa duplicada.");
        }

        $count = $result['tasks']->count();
        $message = "{$count} tarefa(s) criada(s) a partir do Playbook \"{$playbook->name}\".";
        if (!empty($result['warnings'])) {
            $message .= ' Atenção: ' . implode(' ', $result['warnings']);
        }

        return back()->with('success', $message);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'                        => 'required|string|max:150',
            'description'                 => 'nullable|string',
            'disciplines'                 => 'nullable|array',
            'disciplines.*'               => 'in:' . implode(',', array_keys(\App\Models\Project::$disciplines)),
            'tasks'                       => 'required|array|min:1',
            'tasks.*.title'               => 'required|string|max:300',
            'tasks.*.description'         => 'nullable|string',
            'tasks.*.task_type'           => 'required|in:' . implode(',', array_keys(Task::$types)),
            'tasks.*.destination'         => 'nullable|in:' . implode(',', array_keys(Task::$destinations)),
            'tasks.*.priority'            => 'nullable|in:' . implode(',', array_keys(Task::$priorities)),
            'tasks.*.due_offset_days'     => 'nullable|integer|min:0|max:3650',
            'tasks.*.functional_role_id'  => 'nullable|uuid|exists:pgsql.functional_roles,id',
            'tasks.*.phase_index'         => 'nullable|integer|min:0',
            // Fases-modelo da linha do tempo (opcionais). phase_index da tarefa = posição aqui.
            'phases'                      => 'nullable|array|max:40',
            'phases.*.name'               => 'required|string|max:150',
            'phases.*.is_milestone'       => 'nullable|boolean',
            'phases.*.start_offset_days'  => 'nullable|integer|min:0|max:3650',
            'phases.*.duration_days'      => 'nullable|integer|min:1|max:3650',
        ]);
    }

    // Estratégia simples pra update: apaga e recria em vez de diff — o
    // formulário sempre reenvia a lista completa, então não há perda de dado.
    // Fases antes das tarefas: a tarefa aponta pra fase pela posição dela no formulário.
    private function syncTasks(ProjectPlaybook $playbook, array $tasks, array $phases = []): void
    {
        $playbook->tasks()->delete();
        $playbook->phases()->delete();

        $phaseIds = [];
        foreach (array_values($phases) as $i => $item) {
            $milestone = !empty($item['is_milestone']);
            $created = $playbook->phases()->create([
                'position'          => $i + 1,
                'name'              => trim($item['name']),
                'is_milestone'      => $milestone,
                'start_offset_days' => $item['start_offset_days'] ?? null,
                'duration_days'     => $milestone ? null : ($item['duration_days'] ?? null),
            ]);
            // Marco não tem tarefa — tarefa apontando pra marco fica sem fase.
            $phaseIds[$i] = $milestone ? null : $created->id;
        }

        foreach (array_values($tasks) as $i => $item) {
            $phaseIndex = isset($item['phase_index']) && $item['phase_index'] !== '' ? (int) $item['phase_index'] : null;

            $playbook->tasks()->create([
                'project_playbook_phase_id' => $phaseIndex !== null ? ($phaseIds[$phaseIndex] ?? null) : null,
                'position'            => $i + 1,
                'title'               => $item['title'],
                'description'         => $item['description'] ?? null,
                'task_type'           => $item['task_type'],
                'destination'         => $item['destination'] ?? null,
                'priority'            => $item['priority'] ?? null,
                'due_offset_days'     => $item['due_offset_days'] ?? null,
                'functional_role_id'  => $item['functional_role_id'] ?? null,
            ]);
        }
    }

    private function functionalRoles()
    {
        return FunctionalRole::where('organization_id', app('currentOrganization')->id)
            ->orderBy('name')
            ->get();
    }
}

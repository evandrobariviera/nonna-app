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
        $playbooks = ProjectPlaybook::withCount('tasks')->orderBy('name')->get();

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

            $this->syncTasks($playbook, $data['tasks'] ?? []);

            return $playbook;
        });

        return redirect()->route('playbooks.index')->with('success', "Playbook \"{$playbook->name}\" criado.");
    }

    public function edit(ProjectPlaybook $playbook)
    {
        $playbook->load('tasks');

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

            $this->syncTasks($playbook, $data['tasks'] ?? []);
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
        ]);
    }

    // Estratégia simples pra update: apaga e recria em vez de diff — o
    // formulário sempre reenvia a lista completa, então não há perda de dado.
    private function syncTasks(ProjectPlaybook $playbook, array $tasks): void
    {
        $playbook->tasks()->delete();

        foreach (array_values($tasks) as $i => $item) {
            $playbook->tasks()->create([
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

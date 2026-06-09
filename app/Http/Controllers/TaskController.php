<?php

namespace App\Http\Controllers;

use App\Models\MacroPlan;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function store(Request $request, MacroPlan $macroplan, Project $project)
    {
        abort_unless($project->macro_plan_id === $macroplan->id, 403);

        $data = $request->validate([
            'title'       => 'required|string|max:300',
            'description' => 'nullable|string',
            'task_type'   => 'required|in:' . implode(',', array_keys(Task::$types)),
            'executor_id' => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
            'status'      => 'nullable|in:' . implode(',', array_keys(Task::$statuses)),
        ]);

        $project->tasks()->create([
            ...$data,
            'macro_plan_id' => $macroplan->id,
            'client_id'     => $project->client_id,
            'status'        => $data['status'] ?? 'backlog',
            'origin'        => 'projeto',
            'created_by'    => Auth::id(),
        ]);

        return redirect()->route('macroplans.projects.show', [$macroplan, $project])
            ->with('success', 'Tarefa criada.');
    }

    public function update(Request $request, MacroPlan $macroplan, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 403);

        $data = $request->validate([
            'title'             => 'required|string|max:300',
            'description'       => 'nullable|string',
            'task_type'         => 'required|in:' . implode(',', array_keys(Task::$types)),
            'executor_id'       => 'nullable|exists:users,id',
            'due_date'          => 'nullable|date',
            'approval_date'     => 'nullable|date',
            'publish_date'      => 'nullable|date',
            'situation'         => 'nullable|string|max:150',
            'approval_location' => 'nullable|string|max:100',
            'status'            => 'required|in:' . implode(',', array_keys(Task::$statuses)),
        ]);

        $task->update($data);

        return redirect()->route('macroplans.projects.show', [$macroplan, $project])
            ->with('success', 'Tarefa atualizada.');
    }

    public function updateStatus(Request $request, MacroPlan $macroplan, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 403);

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Task::$statuses)),
        ]);

        $task->update(['status' => $data['status']]);

        return redirect()->route('macroplans.projects.show', [$macroplan, $project])
            ->with('success', 'Status atualizado.');
    }

    public function destroy(MacroPlan $macroplan, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 403);
        $task->delete();

        return redirect()->route('macroplans.projects.show', [$macroplan, $project])
            ->with('success', 'Tarefa removida.');
    }
}

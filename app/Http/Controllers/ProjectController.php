<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function dashboard(Request $request)
    {
        $projects = Project::with(['macroPlan.client', 'client', 'tasks'])
            ->orderBy('created_at', 'desc')
            ->get();

        $today = now()->toDateString();

        $data = $projects->map(function (Project $p) use ($today) {
            $tasks        = $p->tasks;
            $activeTasks  = $tasks->whereNotIn('status', ['cancelado']);
            $total        = $activeTasks->count();
            $done         = $tasks->where('status', 'concluido')->count();
            $overdue      = $activeTasks
                ->whereNotIn('status', ['concluido'])
                ->filter(fn($t) => $t->due_date && $t->due_date->toDateString() < now()->toDateString())
                ->count();

            $colCounts = [];
            foreach (Task::$kanbanColumns as $colKey => $col) {
                $colCounts[$colKey] = $tasks->filter(fn($t) => in_array($t->status, $col['statuses']))->count();
            }

            $progress = $total > 0 ? (int) round(($done / $total) * 100) : 0;

            $client     = $p->client ?? $p->macroPlan?->client;
            $clientId   = $p->client_id ?? $p->macroPlan?->client_id;
            $hasPlan    = (bool) $p->macro_plan_id;

            return [
                'id'               => $p->id,
                'title'            => $p->title,
                'objective'        => $p->objective,
                'status'           => $p->status,
                'status_label'     => $p->statusLabel(),
                'status_color'     => $p->statusColor(),
                'type'             => $p->type ?? 'projeto',
                'type_label'       => $p->typeLabel(),
                'type_color'       => $p->typeColor(),
                'client_id'        => $clientId,
                'client_name'      => $client?->company_name ?? '—',
                'macroplan_id'     => $p->macro_plan_id,
                'macroplan_title'  => $p->macroPlan?->title ?? '—',
                'disciplines'      => $p->disciplines ?? [],
                'discipline_labels'=> $p->disciplineLabels(),
                'progress'         => $progress,
                'total_tasks'      => $total,
                'done_tasks'       => $done,
                'overdue_tasks'    => $overdue,
                'col_counts'       => $colCounts,
                'has_overdue'      => $overdue > 0,
                'not_started'      => $total === 0,
                'url'              => $hasPlan
                    ? route('macroplans.projects.show', [$p->macro_plan_id, $p->id])
                    : null,
                'macroplan_url'    => $hasPlan
                    ? route('macroplans.edit', [$p->macro_plan_id, 'bloco' => 'bloco3'])
                    : null,
                'edit_url'         => route('projects.quickUpdate', $p->id),
            ];
        });

        $clients    = Client::orderBy('company_name')->get(['id', 'company_name']);
        $macroplans = MacroPlan::orderBy('title')->get(['id', 'title', 'client_id']);

        $stats = [
            'total'       => $data->count(),
            'active'      => $data->where('status', 'active')->count(),
            'overdue'     => $data->where('has_overdue', true)->count(),
            'not_started' => $data->where('not_started', true)->count(),
            'completed'   => $data->where('status', 'completed')->count(),
            'projetos'    => $data->where('type', 'projeto')->count(),
            'campanhas'   => $data->where('type', 'campanha')->count(),
        ];

        return view('projects.dashboard', [
            'projectsJson'  => $data->values()->toJson(),
            'macroplansJson'=> $macroplans->toJson(),
            'clients'       => $clients,
            'stats'         => $stats,
        ]);
    }

    public function show(MacroPlan $macroplan, Project $project)
    {
        abort_unless($project->macro_plan_id === $macroplan->id, 403);

        $project->load(['tasks.executor', 'tasks.executors', 'macroPlan.client']);
        $users = User::orderBy('name')->get(['id', 'name']);

        // Agrupar tarefas por coluna do kanban
        $kanban = [];
        foreach (Task::$kanbanColumns as $colKey => $col) {
            $kanban[$colKey] = $project->tasks
                ->filter(fn($t) => in_array($t->status, $col['statuses']))
                ->values();
        }
        $cancelled = $project->tasks->where('status', 'cancelado')->values();

        $progress = $project->progressPercent();
        $totalTasks = $project->tasks->where('status', '!=', 'cancelado')->count();
        $doneTasks  = $project->tasks->where('status', 'concluido')->count();

        return view('macroplans.project-show', compact(
            'macroplan', 'project', 'users', 'kanban', 'cancelled', 'progress', 'totalTasks', 'doneTasks'
        ));
    }

    public function store(Request $request, MacroPlan $macroplan)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:200',
            'objective'  => 'nullable|string',
            'disciplines'=> 'nullable|array',
            'disciplines.*' => 'in:criacao,web,trafego,setup,social,seo,email',
        ]);

        $position = $macroplan->projects()->max('position') + 1;

        $macroplan->projects()->create([
            'client_id'  => $macroplan->client_id,
            'position'   => $position,
            'title'      => $data['title'],
            'objective'  => $data['objective'] ?? null,
            'disciplines'=> $data['disciplines'] ?? [],
            'status'     => 'draft',
        ]);

        return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => 'bloco3'])
            ->with('success', 'Projeto adicionado.');
    }

    public function update(Request $request, MacroPlan $macroplan, Project $project)
    {
        abort_unless($project->macro_plan_id === $macroplan->id, 403);

        $data = $request->validate([
            'title'             => 'required|string|max:200',
            'objective'         => 'nullable|string',
            'disciplines'       => 'nullable|array',
            'disciplines.*'     => 'in:criacao,web,trafego,setup,social,seo,email',
            'briefing_criacao'  => 'nullable|string',
            'briefing_web'      => 'nullable|string',
            'briefing_trafego'  => 'nullable|string',
            'briefing_setup'    => 'nullable|string',
            'briefing_social'   => 'nullable|string',
            'briefing_seo'      => 'nullable|string',
            'briefing_email'    => 'nullable|string',
            'status'            => 'required|in:draft,active,completed,cancelled',
        ]);

        if (!isset($data['disciplines'])) {
            $data['disciplines'] = [];
        }

        $project->update($data);

        return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => 'bloco3'])
            ->with('success', 'Projeto atualizado.');
    }

    public function destroy(MacroPlan $macroplan, Project $project)
    {
        abort_unless($project->macro_plan_id === $macroplan->id, 403);
        $project->delete();

        return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => 'bloco3'])
            ->with('success', 'Projeto removido.');
    }

    public function quickUpdate(Request $request, Project $project): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'type'          => 'nullable|in:projeto,campanha',
            'status'        => 'nullable|in:draft,active,completed,cancelled',
            'macro_plan_id' => 'nullable|uuid|exists:pgsql.macro_plans,id',
            'title'         => 'nullable|string|max:200',
        ]);

        $project->update(array_filter($data, fn($v) => $v !== null));

        $project->refresh()->load(['macroPlan', 'client']);
        $client  = $project->client ?? $project->macroPlan?->client;
        $hasPlan = (bool) $project->macro_plan_id;

        return response()->json([
            'success'        => true,
            'type'           => $project->type,
            'type_label'     => $project->typeLabel(),
            'type_color'     => $project->typeColor(),
            'status'         => $project->status,
            'status_label'   => $project->statusLabel(),
            'status_color'   => $project->statusColor(),
            'macroplan_id'   => $project->macro_plan_id,
            'macroplan_title'=> $project->macroPlan?->title ?? '—',
            'client_name'    => $client?->company_name ?? '—',
            'url'            => $hasPlan ? route('macroplans.projects.show', [$project->macro_plan_id, $project->id]) : null,
            'macroplan_url'  => $hasPlan ? route('macroplans.edit', [$project->macro_plan_id, 'bloco' => 'bloco3']) : null,
        ]);
    }
}

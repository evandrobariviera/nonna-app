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
            ->when(!$request->boolean('mostrar_inativos'), fn ($q) => $q->whereHas('client', fn ($c) => $c->where('status', '!=', 'inactive')))
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
            foreach (Task::$statuses as $statusKey => $meta) {
                $colCounts[$statusKey] = $tasks->where('status', $statusKey)->count();
            }

            $progress = $total > 0 ? (int) round(($done / $total) * 100) : 0;

            $client     = $p->client ?? $p->macroPlan?->client;
            $clientId   = $p->client_id ?? $p->macroPlan?->client_id;
            $hasPlan    = (bool) $p->macro_plan_id;

            $deadlineOverdue = $p->end_date
                && $p->end_date->isPast()
                && !in_array($p->status, ['concluido', 'cancelado']);

            return [
                'id'               => $p->id,
                'title'            => $p->title,
                'objective'        => $p->objective,
                'status'           => $p->status,
                'status_label'     => $p->statusLabel(),
                'status_color'     => $p->statusColor(),
                'start_date'       => $p->start_date?->format('d/m/y'),
                'end_date'         => $p->end_date?->format('d/m/y'),
                'deadline_overdue' => $deadlineOverdue,
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
                    : route('projects.showDirect', $p->id),
                'macroplan_url'    => $hasPlan
                    ? route('macroplans.edit', [$p->macro_plan_id, 'bloco' => 'bloco3'])
                    : null,
                'edit_url'         => route('projects.quickUpdate', $p->id),
            ];
        });

        $clients    = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
        $macroplans = MacroPlan::orderBy('title')->get(['id', 'title', 'client_id']);

        $stats = [
            'total'       => $data->count(),
            'active'      => $data->whereNotIn('status', ['concluido', 'cancelado'])->count(),
            'overdue'     => $data->where('has_overdue', true)->count(),
            'not_started' => $data->where('not_started', true)->count(),
            'completed'   => $data->where('status', 'concluido')->count(),
            'projetos'    => $data->where('type', 'projeto')->count(),
            'campanhas'   => $data->where('type', 'campanha')->count(),
        ];

        return view('projects.dashboard', [
            'projectsJson'         => $data->values()->toJson(),
            'macroplansJson'       => $macroplans->toJson(),
            'clients'              => $clients,
            'stats'                => $stats,
            'statusOptionsJson'    => collect(Project::$statuses)
                ->map(fn ($s, $key) => ['key' => $key, 'label' => $s['label'], 'color' => $s['color']])
                ->values()
                ->toJson(),
            'disciplineLabelsJson' => collect(Project::$disciplines)->toJson(),
        ]);
    }

    public function show(MacroPlan $macroplan, Project $project)
    {
        abort_unless($project->macro_plan_id === $macroplan->id, 403);

        $project->load(['tasks.executor', 'tasks.executors', 'macroPlan.client']);
        $users = User::orderBy('name')->get(['id', 'name']);

        $kanban = [];
        foreach (Task::$statuses as $statusKey => $meta) {
            $kanban[$statusKey] = $project->tasks
                ->where('status', $statusKey)
                ->values();
        }
        $cancelled  = $project->tasks->where('status', 'cancelado')->values();
        $progress   = $project->progressPercent();
        $totalTasks = $project->tasks->where('status', '!=', 'cancelado')->count();
        $doneTasks  = $project->tasks->where('status', 'concluido')->count();
        $standalone = false;

        $clientMacroplans = MacroPlan::where('client_id', $project->client_id)->orderBy('title')->get(['id', 'title']);

        return view('macroplans.project-show', compact(
            'macroplan', 'project', 'users', 'kanban', 'cancelled', 'progress', 'totalTasks', 'doneTasks', 'standalone', 'clientMacroplans'
        ));
    }

    /**
     * Preview leve para o painel lateral (canvas) — não a página completa.
     */
    public function preview(Project $project)
    {
        $project->load(['client', 'macroPlan']);

        $recentTasks = $project->tasks()
            ->where('status', '!=', 'cancelado')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'title', 'status', 'due_date']);

        $progress   = $project->progressPercent();
        $totalTasks = $project->tasks()->where('status', '!=', 'cancelado')->count();
        $doneTasks  = $project->tasks()->where('status', 'concluido')->count();

        return view('macroplans._project-preview', compact('project', 'recentTasks', 'progress', 'totalTasks', 'doneTasks'));
    }

    public function showDirect(Project $project)
    {
        $project->load(['tasks.executor', 'tasks.executors', 'macroPlan.client']);
        $macroplan  = $project->macroPlan;
        $users      = User::orderBy('name')->get(['id', 'name']);

        $kanban = [];
        foreach (Task::$statuses as $statusKey => $meta) {
            $kanban[$statusKey] = $project->tasks
                ->where('status', $statusKey)
                ->values();
        }
        $cancelled  = $project->tasks->where('status', 'cancelado')->values();
        $progress   = $project->progressPercent();
        $totalTasks = $project->tasks->where('status', '!=', 'cancelado')->count();
        $doneTasks  = $project->tasks->where('status', 'concluido')->count();
        $standalone = true;

        $clientMacroplans = MacroPlan::where('client_id', $project->client_id)->orderBy('title')->get(['id', 'title']);

        return view('macroplans.project-show', compact(
            'macroplan', 'project', 'users', 'kanban', 'cancelled', 'progress', 'totalTasks', 'doneTasks', 'standalone', 'clientMacroplans'
        ));
    }

    // Reatribui o projeto a um planejamento (ou desvincula, se null) direto
    // da própria página do projeto — antes só dava pra fazer isso pelo modal
    // da listagem /projetos. Redireciona pra URL canônica correta (com ou
    // sem macroplano na rota), já que show() valida que a URL bate com o
    // macro_plan_id atual do projeto.
    public function updateMacroplan(Request $request, Project $project)
    {
        $data = $request->validate(['macro_plan_id' => 'nullable|uuid|exists:pgsql.macro_plans,id']);

        if ($data['macro_plan_id']) {
            $macroplan = MacroPlan::findOrFail($data['macro_plan_id']);
            abort_unless((string) $project->client_id === (string) $macroplan->client_id, 422, 'Esse planejamento é de outro cliente.');
        }

        $project->update(['macro_plan_id' => $data['macro_plan_id']]);

        $redirect = $data['macro_plan_id']
            ? redirect()->route('macroplans.projects.show', [$data['macro_plan_id'], $project])
            : redirect()->route('projects.showDirect', $project);

        return $redirect->with('success', 'Planejamento atualizado.');
    }

    public function store(Request $request, MacroPlan $macroplan)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:200',
            'type'          => 'nullable|in:projeto,campanha',
            'objective'     => 'nullable|string',
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date',
            'disciplines'   => 'nullable|array',
            'disciplines.*' => 'in:criacao,web,trafego,setup,social,seo,email',
        ]);

        $position = $macroplan->projects()->max('position') + 1;

        $macroplan->projects()->create([
            'client_id'  => $macroplan->client_id,
            'position'   => $position,
            'title'      => $data['title'],
            'type'       => $data['type'] ?? 'projeto',
            'objective'  => $data['objective'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date'   => $data['end_date'] ?? null,
            'disciplines'=> $data['disciplines'] ?? [],
            'status'     => 'em_planejamento',
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
            'type'              => 'nullable|in:projeto,campanha',
            'disciplines'       => 'nullable|array',
            'disciplines.*'     => 'in:criacao,web,trafego,setup,social,seo,email',
            'briefing_criacao'  => 'nullable|string',
            'briefing_web'      => 'nullable|string',
            'briefing_trafego'  => 'nullable|string',
            'briefing_setup'    => 'nullable|string',
            'briefing_social'   => 'nullable|string',
            'briefing_seo'      => 'nullable|string',
            'briefing_email'    => 'nullable|string',
            'status'            => 'required|in:' . implode(',', array_keys(Project::$statuses)),
            'tags'              => 'nullable|array',
            'tags.*'            => 'nullable|string|max:100',
            'content_ideas'     => 'nullable|array',
            'traffic_phases'    => 'nullable|array',
            'start_date'        => 'nullable|date',
            'end_date'          => 'nullable|date',
            'budget'            => 'nullable|numeric|min:0',
            // Brief criativo de campanha
            'brief_status'           => 'nullable|in:basico,detalhado',
            'big_idea_titulo'        => 'nullable|string|max:255',
            'big_idea_manifesto'     => 'nullable|string',
            'territorio_alternativo' => 'nullable|string',
            'racional_estrategico'   => 'nullable|string',
            'frase_voz'              => 'nullable|string|max:255',
            'assinatura'             => 'nullable|string|max:255',
            'ponto_atencao'          => 'nullable|string',
            'tom_comunicacao'        => 'nullable|array',
            'angulos'                => 'nullable|array',
            'mecanica'               => 'nullable|array',
            'referencias_visuais'    => 'nullable|array',
            'pecas'                  => 'nullable|array',
        ]);

        $data['disciplines']    = $data['disciplines'] ?? [];
        $data['tags']           = array_values(array_filter($data['tags'] ?? []));
        $data['content_ideas']  = $data['content_ideas'] ?? [];
        $data['traffic_phases'] = $data['traffic_phases'] ?? [];
        $data['tom_comunicacao']     = array_values(array_filter($data['tom_comunicacao'] ?? []));
        $data['angulos']             = $data['angulos'] ?? [];
        $data['mecanica']            = $data['mecanica'] ?? [];
        $data['referencias_visuais'] = $data['referencias_visuais'] ?? [];
        $data['pecas']               = $data['pecas'] ?? [];

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
            'status'        => 'nullable|in:' . implode(',', array_keys(Project::$statuses)),
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
            'url'            => $hasPlan
                ? route('macroplans.projects.show', [$project->macro_plan_id, $project->id])
                : route('projects.showDirect', $project->id),
            'macroplan_url'  => $hasPlan
                ? route('macroplans.edit', [$project->macro_plan_id, 'bloco' => 'bloco3'])
                : null,
        ]);
    }

}

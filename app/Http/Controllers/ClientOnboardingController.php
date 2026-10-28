<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectPlaybook;
use App\Services\ClientFirstCycleService;
use App\Services\Projects\ProjectTimeline;

// "Gestão de Clientes > Entrada (Onboarding)" — desde 2026-10-28 o controle de onboarding é o
// PROJETO Onboarding (fases + tarefas, ClientFirstCycleService). O checklist antigo
// (ClientOnboarding) ficou só como memória da esteira automática do n8n, sem tela.
class ClientOnboardingController extends Controller
{
    public function index()
    {
        $playbookId = ProjectPlaybook::where('name', ClientFirstCycleService::PLAYBOOK_NAME)->value('id');

        $projects = Project::with(['client:id,nickname,company_name,status', 'macroPlan:id,title', 'phases', 'tasks:id,project_id,project_phase_id,status,due_date'])
            ->where('status', '!=', 'cancelado')
            ->where(fn ($q) => $q->where('title', 'Onboarding')->when($playbookId, fn ($w) => $w->orWhere('project_playbook_id', $playbookId)))
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Project $p) => ['project' => $p, 'timeline' => ProjectTimeline::for($p)]);

        [$concluidos, $emAndamento] = $projects->partition(fn ($r) => $r['project']->status === 'concluido');

        return view('onboarding.index', [
            'emAndamento' => $emAndamento->values(),
            'concluidos'  => $concluidos->take(15)->values(),
        ]);
    }

    // Cliente antigo sem projeto Onboarding: cria (no planejamento aberto, ou o primeiro ciclo).
    public function store(Client $client, ClientFirstCycleService $service)
    {
        [$project, $error] = $service->ensureOnboardingProject($client);

        if (!$project) {
            return back()->with('error', $error);
        }

        return redirect()->route('macroplans.projects.show', [$project->macro_plan_id, $project])
            ->with('success', 'Projeto Onboarding pronto.');
    }
}

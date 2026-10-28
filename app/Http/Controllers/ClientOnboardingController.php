<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectPlaybook;
use App\Services\ClientFirstCycleService;
use App\Services\Projects\ProjectTimeline;

// "Gestão de Clientes > Entrada (Onboarding)" e "Saída (Offboarding)" — desde 2026-10-28 os
// dois são PROJETOS (fases + tarefas pelos Playbooks, ClientFirstCycleService). O checklist
// antigo (ClientOnboarding) ficou só como memória da esteira automática do n8n, sem tela.
class ClientOnboardingController extends Controller
{
    public function index()
    {
        return $this->listing('onboarding', 'Onboarding', ClientFirstCycleService::PLAYBOOK_NAME);
    }

    public function offboardingIndex()
    {
        return $this->listing('offboarding', 'Offboarding', ClientFirstCycleService::OFFBOARDING_PLAYBOOK);
    }

    // Cliente antigo sem projeto Onboarding: cria (no planejamento aberto, ou o primeiro ciclo).
    public function store(Client $client, ClientFirstCycleService $service)
    {
        [$project, $error] = $service->ensureOnboardingProject($client);

        return $this->toProject($project, $error, 'Projeto Onboarding pronto.');
    }

    // Inicia a saída do cliente: projeto Offboarding pelo Playbook.
    public function startOffboarding(Client $client, ClientFirstCycleService $service)
    {
        [$project, $error] = $service->startOffboarding($client);

        return $this->toProject($project, $error, 'Offboarding iniciado — siga as fases do projeto.');
    }

    private function toProject(?Project $project, ?string $error, string $success)
    {
        if (!$project) {
            return back()->with('error', $error);
        }

        return redirect()->route('macroplans.projects.show', [$project->macro_plan_id, $project])->with('success', $success);
    }

    // Lista dos projetos de entrada/saída: em andamento + concluídos recentes. Offboarding mostra
    // também cliente já inativo (o último passo é justamente inativar).
    private function listing(string $kind, string $title, string $playbookName)
    {
        $playbookId = ProjectPlaybook::where('name', $playbookName)->value('id');

        $projects = Project::with(['client:id,nickname,company_name,status', 'macroPlan:id,title', 'phases', 'tasks:id,project_id,project_phase_id,status,due_date'])
            ->where('status', '!=', 'cancelado')
            ->where(fn ($q) => $q->where('title', $title)->when($playbookId, fn ($w) => $w->orWhere('project_playbook_id', $playbookId)))
            ->when($kind === 'onboarding', fn ($q) => $q->whereHas('client', fn ($c) => $c->where('status', '!=', 'inactive')))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Project $p) => ['project' => $p, 'timeline' => ProjectTimeline::for($p)]);

        [$concluidos, $emAndamento] = $projects->partition(fn ($r) => $r['project']->status === 'concluido');

        return view('onboarding.index', [
            'kind'        => $kind,
            'emAndamento' => $emAndamento->values(),
            'concluidos'  => $concluidos->take(15)->values(),
        ]);
    }
}

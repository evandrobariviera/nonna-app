<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\MacroPlan;
use App\Models\Project;
use App\Models\Task;
use App\Services\Documents\ClientDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        $client = app('currentPortalClient');

        $macroplans = MacroPlan::where('client_id', $client->id)
            ->with('projects')
            ->latest('period_start')
            ->get();

        return view('portal.projects.index', compact('client', 'macroplans'));
    }

    public function show(MacroPlan $macroplan): View
    {
        $client = app('currentPortalClient');

        abort_if($macroplan->client_id !== $client->id, 403);

        $macroplan->load(['projects.tasks' => fn($q) => $q->orderBy('created_at')]);

        $document      = $macroplan->mainDocument();
        $fieldDocument = ClientDocuments::forPlan($macroplan);

        return view('portal.projects.show', compact('client', 'macroplan', 'document', 'fieldDocument'));
    }

    // Documento em campos do planejamento, na versão do cliente (só em execução ou concluído).
    public function planDocument(MacroPlan $macroplan)
    {
        abort_if($macroplan->client_id !== app('currentPortalClient')->id, 403);
        $doc = ClientDocuments::forPlan($macroplan);
        abort_unless($doc, 404);

        return ClientDocuments::view($doc);
    }

    // Link assinado gerado só no clique: abrir o card não pede um link por campanha.
    public function document(Project $project)
    {
        abort_if($project->client_id !== app('currentPortalClient')->id, 403);

        // Documento em campos (24/11) tem prioridade: é a versão viva da campanha/projeto.
        if ($field = ClientDocuments::forProject($project)) {
            return ClientDocuments::view($field);
        }

        $document = $project->mainDocument();
        abort_unless($document, 404);

        return redirect()->away($document->url());
    }

    public function showTask(Task $task): View
    {
        $client = app('currentPortalClient');

        abort_if($task->client_id !== $client->id || $task->is_ticket, 403);

        $task->load('comments.user', 'comments.contact', 'project.macroPlan');
        $deliverables = $task->attachments()->where('is_deliverable', true)->get();

        return view('portal.projects.task-show', compact('client', 'task', 'deliverables'));
    }
}

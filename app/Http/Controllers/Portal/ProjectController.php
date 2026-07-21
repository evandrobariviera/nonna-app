<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\MacroPlan;
use App\Models\Task;
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

        return view('portal.projects.show', compact('client', 'macroplan'));
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

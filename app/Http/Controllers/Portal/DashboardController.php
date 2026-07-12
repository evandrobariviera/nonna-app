<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\TaskApprovalRound;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $client = auth()->user()->client()->with([
            'macroplans.projects.tasks',
        ])->firstOrFail();

        $allPlans    = $client->macroplans;
        // "em_execucao" é o status real de MacroPlan/Project — "active" nunca
        // existiu nas duas taxonomias, então "Planejamento Ativo" nunca aparecia.
        $activePlan  = $allPlans->where('status', 'em_execucao')->sortByDesc('period_start')->first();

        $allProjects = $allPlans->flatMap->projects;
        $allTasks    = $allProjects->flatMap->tasks;

        $stats = [
            'plans_total'     => $allPlans->count(),
            'plans_active'    => $allPlans->where('status', 'em_execucao')->count(),
            'projects_total'  => $allProjects->count(),
            'projects_active' => $allProjects->whereIn('status', ['em_execucao'])->count(),
            'tasks_total'     => $allTasks->count(),
            'tasks_done'      => $allTasks->where('status', 'concluido')->count(),
            'tasks_progress'  => $allTasks->whereIn('status', ['em_producao', 'revisao_interna', 'ajuste_alteracao', 'aprovacao', 'despacho_agendamento'])->count(),
            'tasks_cancelled' => $allTasks->where('status', 'cancelado')->count(),
        ];

        $activePlanTasks = $activePlan?->projects->flatMap->tasks ?? collect();
        $activePlanStats = [
            'total'    => $activePlanTasks->count(),
            'done'     => $activePlanTasks->where('status', 'concluido')->count(),
            'progress' => 0,
        ];
        if ($activePlanStats['total'] > 0) {
            $activePlanStats['progress'] = round($activePlanStats['done'] / $activePlanStats['total'] * 100);
        }

        $nextMeeting = Meeting::where('client_id', $client->id)
            ->whereIn('status', ['para_agendar', 'agendada'])
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->first();

        $openTicketsCount = Task::where('is_ticket', true)
            ->where('client_id', $client->id)
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->count();

        $pendingApprovalsCount = TaskApprovalRound::whereHas('task', fn ($q) => $q->where('client_id', $client->id))
            ->where('status', 'pending')
            ->count();

        return view('portal.dashboard', compact('client', 'activePlan', 'stats', 'activePlanStats', 'nextMeeting', 'openTicketsCount', 'pendingApprovalsCount'));
    }
}

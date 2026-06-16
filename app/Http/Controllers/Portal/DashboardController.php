<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $client = auth()->user()->client()->with([
            'macroplans.projects.tasks',
        ])->firstOrFail();

        $allPlans    = $client->macroplans;
        $activePlan  = $allPlans->where('status', 'active')->sortByDesc('period_start')->first();

        $allProjects = $allPlans->flatMap->projects;
        $allTasks    = $allProjects->flatMap->tasks;

        $stats = [
            'plans_total'     => $allPlans->count(),
            'plans_active'    => $allPlans->where('status', 'active')->count(),
            'projects_total'  => $allProjects->count(),
            'projects_active' => $allProjects->whereIn('status', ['active'])->count(),
            'tasks_total'     => $allTasks->count(),
            'tasks_done'      => $allTasks->where('status', 'concluido')->count(),
            'tasks_progress'  => $allTasks->whereIn('status', ['em_copy', 'pronto_producao', 'em_producao', 'revisao', 'aguardando_envio', 'aguardando_resposta', 'ajuste'])->count(),
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

        return view('portal.dashboard', compact('client', 'activePlan', 'stats', 'activePlanStats'));
    }
}

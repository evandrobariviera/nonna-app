<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Models\UserLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

// Painel administrativo (org.admin) que mostra, por usuário e por dia, o que
// cada um fez no sistema — login e ações rastreadas em TaskActivity (mudança
// de status/situação/etc). Não é um relatório de produtividade (isso já
// existe em /produtividade); é um "diário" cronológico por pessoa.
class WorkMonitorController extends Controller
{
    public function index(Request $request): View
    {
        $org = app('currentOrganization');

        $date = Carbon::parse($request->get('date', today()->toDateString()))->startOfDay();

        $members = $org->users()->orderBy('name')->get(['users.id', 'users.name']);
        $memberIds = $members->pluck('id');

        $userIdFilter = $request->get('user_id');
        $filteredIds = $userIdFilter ? $memberIds->filter(fn ($id) => (string) $id === (string) $userIdFilter) : $memberIds;

        // TaskActivity/UserLogin não têm organization_id próprio — escopa via
        // task_id (o OrganizationScope de Task já resolveria) e via membros da
        // organização atual, mesmo padrão do ProductivityDashboardController.
        $activities = TaskActivity::whereIn('task_id', Task::pluck('id'))
            ->whereIn('user_id', $filteredIds)
            ->whereBetween('created_at', [$date, $date->copy()->endOfDay()])
            ->with('task.client')
            ->get();

        $logins = UserLogin::whereIn('user_id', $filteredIds)
            ->whereBetween('logged_in_at', [$date, $date->copy()->endOfDay()])
            ->get();

        $timelineByUser = $this->buildTimelines($members->whereIn('id', $filteredIds), $activities, $logins);

        return view('work-monitor.index', [
            'date'           => $date,
            'members'        => $members,
            'selectedUserId' => $userIdFilter,
            'timelines'      => $timelineByUser,
        ]);
    }

    /**
     * Monta, por usuário, a lista cronológica de eventos do dia (login +
     * ações) já ordenada, mais um resumo (1º login, total de ações).
     */
    private function buildTimelines(Collection $members, Collection $activities, Collection $logins): Collection
    {
        $activitiesByUser = $activities->groupBy('user_id');
        $loginsByUser = $logins->groupBy('user_id');

        return $members->map(function (User $member) use ($activitiesByUser, $loginsByUser) {
            $events = collect();

            foreach ($activitiesByUser->get($member->id, []) as $activity) {
                $events->push((object) [
                    'type'  => 'activity',
                    'at'    => $activity->created_at,
                    'model' => $activity,
                ]);
            }

            foreach ($loginsByUser->get($member->id, []) as $login) {
                $events->push((object) [
                    'type'  => 'login',
                    'at'    => $login->logged_in_at,
                    'model' => $login,
                ]);
            }

            $events = $events->sortByDesc('at')->values();
            $firstLogin = $loginsByUser->get($member->id, collect())->sortBy('logged_in_at')->first();

            return (object) [
                'user'         => $member,
                'events'       => $events,
                'first_login'  => $firstLogin?->logged_in_at,
                'action_count' => $activitiesByUser->get($member->id, collect())->count(),
            ];
        })
            // Quem teve algum evento primeiro (ordenado por nome dentro do grupo), depois
            // quem não teve nada — "sem eventos" ainda entra na lista (sinal útil: não
            // logou/não mexeu em nada naquele dia), só fica no fim.
            ->partition(fn ($timeline) => $timeline->events->isNotEmpty())
            ->map(fn (Collection $group) => $group->sortBy(fn ($t) => $t->user->name)->values())
            ->pipe(fn (Collection $groups) => $groups->get(0)->concat($groups->get(1)));
    }
}

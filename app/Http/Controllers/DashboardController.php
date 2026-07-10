<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\Sprint;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $activeSprint = Sprint::where('status', 'active')->first();
        if ($activeSprint) {
            $activeSprint->load('tasks');
            $sprintTotal = $activeSprint->tasks->whereNotIn('status', ['cancelado'])->count();
            $sprintDone  = $activeSprint->tasks->where('status', 'concluido')->count();
            $sprintProgress = $sprintTotal > 0 ? (int) round(($sprintDone / $sprintTotal) * 100) : 0;
        } else {
            $sprintTotal = $sprintDone = $sprintProgress = 0;
        }

        // Camada operacional: minhas tarefas por etapa (sempre como executor)
        $myAdjustmentTasks = $this->executorTasksQuery($userId)
            ->where('status', 'ajuste_alteracao')
            ->with('client')->orderBy('due_date')->limit(8)->get();

        $myProductionTasks = $this->executorTasksQuery($userId)
            ->where('status', 'em_producao')
            ->with('client')->orderBy('due_date')->limit(8)->get();

        $myReadyForProductionTasks = $this->executorTasksQuery($userId)
            ->where('status', 'backlog')
            ->where('situation', 'Pronto para produção')
            ->with('client')->orderBy('due_date')->limit(8)->get();

        // Agenda: qualquer reunião futura que ainda não aconteceu (não só "Agendada" —
        // "Para Agendar" também conta, é o status padrão de uma reunião recém-criada).
        $myMeetings = Meeting::where(function ($q) use ($userId) {
                $q->where('organized_by', $userId)
                  ->orWhereHas('participants', fn ($q2) => $q2->where('users.id', $userId));
            })
            ->whereNotIn('status', ['realizada', 'cancelada'])
            ->where('scheduled_at', '>=', now())
            ->with('client')
            ->orderBy('scheduled_at')
            ->limit(6)
            ->get();

        return view('dashboard', compact(
            'activeSprint', 'sprintTotal', 'sprintDone', 'sprintProgress',
            'myAdjustmentTasks', 'myProductionTasks', 'myReadyForProductionTasks',
            'myMeetings'
        ));
    }

    private function executorTasksQuery(string $userId): Builder
    {
        return Task::where(function ($q) use ($userId) {
            $q->where('executor_id', $userId)
              ->orWhereHas('executors', fn ($q2) => $q2->where('users.id', $userId));
        });
    }
}

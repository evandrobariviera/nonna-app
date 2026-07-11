<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\Meeting;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskApprovalRound;
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

        // Agenda: só os compromissos de hoje (não só "Agendada" — "Para Agendar"
        // também conta, é o status padrão de uma reunião recém-criada).
        $myMeetings = Meeting::where(function ($q) use ($userId) {
                $q->where('organized_by', $userId)
                  ->orWhereHas('participants', fn ($q2) => $q2->where('users.id', $userId));
            })
            ->whereNotIn('status', ['realizada', 'cancelada'])
            ->whereDate('scheduled_at', today())
            ->with('client')
            ->orderBy('scheduled_at')
            ->limit(6)
            ->get();

        // ── Seção "Estratégia" ──
        $today = today();

        $meetingsPosReuniao = Meeting::where('status', 'pos_reuniao')
            ->with('client')->orderBy('scheduled_at')->limit(8)->get();

        $meetingsRealizadas = Meeting::where('status', 'realizada')
            ->with('client')->orderByDesc('scheduled_at')->limit(8)->get();

        // Cliente ativo, com Tráfego Pago ou Consultoria Estratégica contratado, cujo
        // macroplanejamento mais recente não está "em execução" (nunca teve um, ou o
        // último ciclo já foi encerrado/ainda não começou).
        $clientsWithoutActivePlan = Client::where('status', 'active')
            ->where(function ($q) {
                $q->whereJsonContains('contracted_services', 'trafego')
                  ->orWhereJsonContains('contracted_services', 'consultoria');
            })
            ->whereDoesntHave('macroplans', fn ($q) => $q->where('status', 'em_execucao'))
            ->orderBy('company_name')
            ->get();

        $plansExpiringSoon = MacroPlan::where('status', '!=', 'concluido')
            ->whereBetween('period_end', [$today, $today->copy()->addDays(30)])
            ->with('client')
            ->orderBy('period_end')
            ->get();

        $activePlans = MacroPlan::where('status', 'em_execucao')
            ->with('client')
            ->orderBy('period_end')
            ->get();

        // ── Seção "Atendimento" ──
        $teamMeetingsToday = Meeting::whereNotIn('status', ['cancelada'])
            ->whereDate('scheduled_at', $today)
            ->with('client')
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get();

        $openTickets = Task::where('is_ticket', true)
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->with('client')
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        $roundsPending = TaskApprovalRound::where('status', 'pending')
            ->with('task.client')
            ->orderByDesc('submitted_at')
            ->limit(8)
            ->get();

        $roundsApproved = TaskApprovalRound::where('status', 'approved')
            ->with('task.client')
            ->orderByDesc('resolved_at')
            ->limit(8)
            ->get();

        $roundsChangesRequested = TaskApprovalRound::where('status', 'changes_requested')
            ->with('task.client')
            ->orderByDesc('resolved_at')
            ->limit(8)
            ->get();

        return view('dashboard', compact(
            'activeSprint', 'sprintTotal', 'sprintDone', 'sprintProgress',
            'myAdjustmentTasks', 'myProductionTasks', 'myReadyForProductionTasks',
            'myMeetings',
            'meetingsPosReuniao', 'meetingsRealizadas',
            'clientsWithoutActivePlan', 'plansExpiringSoon', 'activePlans',
            'teamMeetingsToday', 'openTickets',
            'roundsPending', 'roundsApproved', 'roundsChangesRequested'
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

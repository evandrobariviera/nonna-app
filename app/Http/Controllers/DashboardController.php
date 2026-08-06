<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Models\InternalNotification;
use App\Models\MacroPlan;
use App\Models\Meeting;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskApprovalRound;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        // Agenda: todos os compromissos pendentes (não só hoje — "Para Agendar"
        // também conta, é o status padrão de uma reunião recém-criada), agrupados
        // por status pro quadro "Agenda" do dashboard. Sem limite: é um recorte
        // pessoal (organizador ou participante), tende a ficar pequeno.
        $myMeetingsAgenda = Meeting::where(function ($q) use ($userId) {
                $q->where('organized_by', $userId)
                  ->orWhereHas('participants', fn ($q2) => $q2->where('users.id', $userId));
            })
            ->whereNotIn('status', ['realizada', 'cancelada'])
            ->with('client')
            ->orderBy('scheduled_at')
            ->get();

        $myMeetingsByStatus = $myMeetingsAgenda->groupBy('status');

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

        // ── Seção "Operação" (papel Atendimento) ──
        $openTickets = Task::where('is_ticket', true)
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->whereNull('sprint_id') // já triado pra uma Sprint = aparece só lá, não duplica aqui
            ->with('client')
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        $roundsPending = TaskApprovalRound::where('status', 'pending')
            ->whereNotNull('sent_at')
            ->with('task.client')
            ->orderByDesc('submitted_at')
            ->limit(8)
            ->get();

        $roundsAwaitingSendCount = TaskApprovalRound::where('status', 'pending')
            ->whereNull('sent_at')
            ->count();

        $roundsApproved = TaskApprovalRound::where('status', 'approved')
            ->with('task.client')
            ->orderByDesc('resolved_at')
            ->limit(8)
            ->get();

        $roundsChangesRequested = TaskApprovalRound::where('status', 'changes_requested')
            ->whereNull('handled_at') // já tratada (roteada de volta pra Sprint) — não é mais "pendente de olhar"
            ->with('task.client')
            ->orderByDesc('resolved_at')
            ->limit(8)
            ->get();

        // ── Seção "Heads" (Criativa & Tech, mesma seção pros dois) ──
        // "Responsável" aqui é o papel dedicado na task_executors (pivot role =
        // 'responsavel'), diferente de "executor" — é quem responde pela qualidade,
        // não quem produz.
        $headsTickets = Task::where('is_ticket', true)
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->whereNull('sprint_id') // já triado pra uma Sprint = aparece só lá, não duplica aqui
            ->whereHas('responsibles', fn ($q) => $q->where('users.id', $userId))
            ->with('client')
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        $headsRevisaoInterna = Task::where('status', 'revisao_interna')
            ->whereHas('responsibles', fn ($q) => $q->where('users.id', $userId))
            ->with('client')
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        // ── Pendências de cadastro (transversal, não é seção por papel) ──
        // Restrito a whereNull('sprint_id') de propósito, pra bater exatamente
        // com o que aparece na Fila quando o usuário clicar no link. Só o total
        // é exibido no dashboard (cardo com o número), sem listagem de amostra.
        $pendingTasksCount = Task::pendente()->whereNull('sprint_id')->count();

        // ── Seção "Mídia Paga" (papel Tráfego) ──
        // Coluna 1: fila compartilhada — gerada pela automação "Notificar Tráfego" (ver
        // /automacoes) sempre que uma tarefa de Campanhas Patrocinadas chega em Despacho ou
        // Concluído. unique('source_id') porque o fan-out cria 1 linha por destinatário do
        // papel Tráfego — aqui é 1 card por tarefa, não por pessoa.
        $creativosProntos = InternalNotification::where('kind', 'criativo_pronto_campanha')
            ->whereIn('status', ['novo', 'lido'])
            ->orderBy('generated_at')
            ->get()
            ->unique('source_id')
            ->values();

        $creativosProntosTasks = Task::whereIn('id', $creativosProntos->pluck('source_id'))
            ->with('client')
            ->get()
            ->keyBy('id');

        $budgetsNeedingAddition = ClientAdAccount::where('budget_status', 'adicao_necessaria')
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->with('client')
            ->orderBy('created_at')
            ->get();

        // `status` só reflete se o anunciante desligou a campanha manualmente na Meta/Google —
        // não pega campanhas que expiraram sozinhas por stop_time (a API não atualiza `status`
        // nesse caso, só o `effective_status`, que não sincronizamos hoje). Por isso, mesmo
        // filtro que Campanhas Patrocinadas já usa: só entra quem teve gasto ou impressão de
        // verdade nos últimos 7 dias — sinal real de que ainda está veiculando.
        $campaignsNeedingOptimization = AdCampaign::where('status', 'active')
            ->whereHas('adAccount.client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('ad_daily_snapshots')
                    ->whereColumn('ad_daily_snapshots.client_ad_account_id', 'ad_campaigns.client_ad_account_id')
                    ->whereColumn('ad_daily_snapshots.entity_id', 'ad_campaigns.external_id')
                    ->where('ad_daily_snapshots.entity_level', 'campaign')
                    ->where('ad_daily_snapshots.snapshot_date', '>=', today()->subDays(7))
                    ->where(fn ($q) => $q->where('ad_daily_snapshots.spend', '>', 0)->orWhere('ad_daily_snapshots.impressions', '>', 0));
            })
            ->with('adAccount.client')
            ->orderByRaw('last_optimized_at ASC NULLS FIRST')
            ->get()
            ->filter(fn ($c) => $c->isOptimizationOverdue())
            ->values();

        return view('dashboard', compact(
            'activeSprint', 'sprintTotal', 'sprintDone', 'sprintProgress',
            'myAdjustmentTasks', 'myProductionTasks', 'myReadyForProductionTasks',
            'myMeetingsByStatus',
            'meetingsPosReuniao', 'meetingsRealizadas',
            'clientsWithoutActivePlan', 'plansExpiringSoon', 'activePlans',
            'openTickets',
            'roundsPending', 'roundsAwaitingSendCount', 'roundsApproved', 'roundsChangesRequested',
            'headsTickets', 'headsRevisaoInterna',
            'pendingTasksCount',
            'creativosProntos', 'creativosProntosTasks', 'budgetsNeedingAddition', 'campaignsNeedingOptimization'
        ));
    }

    // Resolve todas as cópias da notificação (uma por destinatário do papel Tráfego, fan-out)
    // de uma vez — fila compartilhada, qualquer gestor de Tráfego fecha pra todo mundo.
    public function resolveCriativoAlert(Task $task)
    {
        abort_unless(
            in_array('trafego', app('userFunctionRoles', [])) || in_array(app('currentOrgRole'), ['owner', 'admin']),
            403
        );

        InternalNotification::where('kind', 'criativo_pronto_campanha')
            ->where('source_id', $task->id)
            ->whereIn('status', ['novo', 'lido'])
            ->update(['status' => 'resolvido']);

        return redirect()->route('dashboard')->with('success', 'Notificação resolvida.');
    }

    private function executorTasksQuery(string $userId): Builder
    {
        return Task::where(function ($q) use ($userId) {
            $q->where('executor_id', $userId)
              ->orWhereHas('executors', fn ($q2) => $q2->where('users.id', $userId));
        });
    }
}

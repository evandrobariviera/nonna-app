<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Models\FunctionalRole;
use App\Models\InternalNotification;
use App\Models\LiteraryQuote;
use App\Models\MacroPlan;
use App\Models\Meeting;
use App\Models\OrganizationUser;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskApprovalRound;
use App\Models\User;
use App\Services\Dashboard\DistributionCockpit;
use App\Services\Dashboard\PlanningCockpit;
use App\Services\Dashboard\SprintScoreboard;
use App\Support\DashboardModes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // Blocos da Dashboard que cada modo mostra (ver App\Support\DashboardModes). A faixa
    // fixa (pendências pessoais + "Hoje") aparece em qualquer modo, fora desta lista.
    // Visão geral = a Dashboard completa, como era antes dos modos.
    private const MODE_BLOCKS = [
        'execucao'     => ['meus_numeros', 'placar', 'kanban', 'minha_semana'],
        'distribuicao' => ['distribuicao', 'heads'],
        'planejamento' => ['planejamento'],
        'atendimento'  => ['agenda', 'atendimento'],
        'midia_paga'   => ['midia_paga'],
        'visao_geral'  => ['sprint', 'cadastro', 'agenda', 'atendimento', 'heads', 'midia_paga', 'meus_numeros', 'placar', 'kanban', 'estrategia', 'outros_papeis'],
    ];

    public function index(Request $request)
    {
        // "Ver como" — admin/dono enxerga a Dashboard exatamente como outra pessoa vê:
        // mesmos modos, mesmos dados (tudo abaixo usa $userId). Só leitura: a view esconde
        // o que mudaria algo em nome da pessoa (resolver pendência, arrastar card).
        // Nunca abre conversas do chat — a Dashboard não mostra chat.
        [$viewingAs, $subjectPivot] = $this->resolveViewingAs($request);

        if ($viewingAs) {
            $userId         = $viewingAs->id;
            $subjectRoles   = FunctionalRole::where('organization_id', $subjectPivot->organization_id)
                ->whereHas('users', fn ($q) => $q->where('users.id', $viewingAs->id))
                ->pluck('key')->all();
            $subjectIsAdmin = in_array($subjectPivot->role, ['owner', 'admin'], true);
            $availableModes = DashboardModes::availableFor($subjectPivot->dashboard_modes, $subjectRoles, $subjectIsAdmin);
            $mode           = in_array($request->get('modo'), $availableModes, true)
                ? $request->get('modo')
                : DashboardModes::resolveCurrent($subjectPivot->dashboard_mode, $availableModes, $subjectIsAdmin);
        } else {
            $userId         = Auth::id();
            $subjectRoles   = app('userFunctionRoles');
            $subjectIsAdmin = in_array(app('currentOrgRole'), ['owner', 'admin'], true);
            $availableModes = app('dashboardModes');
            $mode           = app('dashboardMode') ?? 'execucao';
        }

        // Quem o admin pode "ver como" — o seletor fica na própria Dashboard.
        $teamMembers = in_array(app('currentOrgRole'), ['owner', 'admin'], true)
            ? app('currentOrganization')->users()->where('users.id', '!=', Auth::id())->orderBy('name')->get(['users.id', 'users.name'])
            : collect();

        $subjectUserId = (int) $userId;

        $blocks = self::MODE_BLOCKS[$mode] ?? self::MODE_BLOCKS['execucao'];
        $show   = fn (string $block) => in_array($block, $blocks, true);

        // Citação literária do dia — fixa (a mesma pra toda a Organização, o
        // dia inteiro), não sorteada a cada carregamento de página. Rotaciona
        // deterministicamente pelo acervo (dias corridos desde uma data fixa,
        // módulo a quantidade de citações ativas) — sem precisar de job
        // agendado nem de coluna pra marcar "já mostrada".
        $literaryQuote = null;
        $quotes = LiteraryQuote::where('is_active', true)->orderBy('created_at')->get();
        if ($quotes->isNotEmpty()) {
            $daysSinceEpoch = \Carbon\Carbon::parse('2026-01-01')->diffInDays(today());
            $literaryQuote = $quotes[$daysSinceEpoch % $quotes->count()];
        }

        // Pendências pessoais — logo abaixo do card da Sprint, propositalmente chamativa
        // (ver dashboard.blade.php). Só "novo" (não "lido"): a seção deve sumir assim que
        // resolvida, não continuar ali só porque foi vista.
        $myNotifications = InternalNotification::where('user_id', $userId)
            ->where('status', 'novo')
            ->orderBy('generated_at')
            ->get();

        $activeSprint = Sprint::where('status', 'active')->first();
        $sprintByStatus = collect();
        if ($activeSprint && $show('sprint')) {
            $activeSprint->load('tasks');
            $sprintTotal = $activeSprint->tasks->whereNotIn('status', ['cancelado'])->count();
            $sprintDone  = $activeSprint->tasks->where('status', 'concluido')->count();
            $sprintProgress = $sprintTotal > 0 ? (int) round(($sprintDone / $sprintTotal) * 100) : 0;
            $sprintByStatus = $activeSprint->tasks
                ->whereNotIn('status', ['cancelado'])
                ->countBy('status');
        } else {
            $sprintTotal = $sprintDone = $sprintProgress = 0;
        }

        // Camada operacional: minhas tarefas por etapa (sempre como executor), só da
        // sprint aberta — Operação é a única tela do time operacional, sem "Ver
        // todas" pra outra página (o time se perdia nela); então mostra tudo aqui
        // mesmo, sem limit(): se tiver dezenas de tarefas, a pessoa vê a régua real
        // do que precisa cumprir. Ordenado/exibido por data de aprovação, não
        // vencimento — é o prazo que importa pra essas etapas (dashboard.blade.php).
        $myAdjustmentTasks = collect();
        $myProductionTasks = collect();
        $myReadyForProductionTasks = collect();

        // Revisão interna esperando EU revisar (sou o Responsável — quem responde pela
        // qualidade, não quem produz). Vira uma coluna a mais no quadro da Execução, entre
        // Ajuste e Em Produção, que só aparece quando tem algo (antes era um card solto na
        // seção Heads da Distribuição — revisar é executar, não distribuir). Sem filtro de
        // sprint: revisão parada é gargalo em qualquer sprint.
        $myReviewTasks = $show('kanban')
            ? Task::where('status', 'revisao_interna')
                ->whereHas('responsibles', fn ($q) => $q->where('users.id', $userId))
                // cliente inativo some (mesma regra de leitura do resto do app); interna fica
                ->where(fn ($q) => $q->whereNull('client_id')->orWhereHas('client', fn ($c) => $c->where('status', '!=', 'inactive')))
                ->with(['client', 'executor', 'executors'])
                ->orderBy('approval_date')
                ->get()
            : collect();

        if ($activeSprint && $show('kanban')) {
            $myAdjustmentTasks =$this->executorTasksQuery($userId)
                ->where('sprint_id', $activeSprint->id)
                ->where('status', 'ajuste_alteracao')
                ->with('client')->orderBy('approval_date')->get();

            $myProductionTasks = $this->executorTasksQuery($userId)
                ->where('sprint_id', $activeSprint->id)
                ->where('status', 'em_producao')
                ->with('client')->orderBy('approval_date')->get();

            $myReadyForProductionTasks = $this->executorTasksQuery($userId)
                ->where('sprint_id', $activeSprint->id)
                ->where('status', 'backlog')
                ->where('situation', 'Pronto para produção')
                ->with('client')->orderBy('approval_date')->get();
        }

        // "Meus Números na Sprint" — recorte pessoal acima dos 3 quadros de Operação:
        // quantas tarefas eu executo na sprint atual e como estão distribuídas por
        // status. Cancelada fica de fora (não é "trabalho" pra contar). Ordem fixa
        // (a mesma de Task::$statuses) e só entra tile de status que a pessoa
        // realmente tem — sem card zerado poluindo a tela.
        $myExecutorSprintByStatus = collect();
        $myExecutorSprintTotal = 0;
        $myExecutorSprintDone  = 0;
        $myPointsTotal = $myPointsDone = 0; // pontos de sprint (métrica híbrida, ao lado da quantidade)

        if ($activeSprint && $show('meus_numeros')) {
            $rows = $this->executorTasksQuery($userId)
                ->where('sprint_id', $activeSprint->id)
                ->where('status', '!=', 'cancelado')
                ->select('status', DB::raw('count(*) as total'), DB::raw('sum(coalesce(sprint_points, 1)) as points'))
                ->groupBy('status')
                ->toBase()->get()->keyBy('status');
            $counts = $rows->map(fn ($r) => (int) $r->total);

            $myExecutorSprintTotal = (int) $counts->sum();
            $myExecutorSprintDone  = (int) ($counts['concluido'] ?? 0);
            $myPointsTotal = (int) $rows->sum('points');
            $myPointsDone  = (int) ($rows['concluido']->points ?? 0);

            $myExecutorSprintByStatus = collect(Task::$statuses)
                ->except(['cancelado'])
                ->map(fn ($meta, $key) => [
                    'label' => $meta['label'],
                    'count' => (int) ($counts[$key] ?? 0),
                    'hex'   => Task::colorHex($meta['color']),
                ])
                ->filter(fn ($row) => $row['count'] > 0)
                ->values();
        }

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

        // ── Faixa "Hoje" (fixa, aparece em qualquer modo) ──
        // Minhas reuniões de hoje + o que está atrasado comigo na sprint (mesma régua do
        // quadro de Operação: sou executor, etapa minha, data de aprovação já passou).
        $myMeetingsToday = $myMeetingsAgenda->filter(fn ($m) => $m->scheduled_at->isToday())->values();

        $myOverdueTasks = $activeSprint
            ? $this->executorTasksQuery($userId)
                ->where('sprint_id', $activeSprint->id)
                ->whereIn('status', ['backlog', 'em_producao', 'ajuste_alteracao'])
                ->whereDate('approval_date', '<', today())
                ->with('client')->orderBy('approval_date')->get()
            : collect();

        // ── Travas (fixa, aparece em qualquer modo, acima do "Hoje") ──
        // Tarefas marcadas como Trava (TaskController::toggleBlocker) em que eu executo OU
        // sou Responsável — o Head também precisa ver o que está segurando o time. Sem
        // filtro de sprint: trava é trava em qualquer sprint. Mais antiga primeiro.
        $myBlockers = Task::whereNotNull('blocker_at')
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->where(fn ($q) => $q
                ->where('executor_id', $userId)
                ->orWhereHas('executors', fn ($q2) => $q2->where('users.id', $userId)->whereIn('task_executors.role', ['executor', 'responsavel'])))
            ->where(fn ($q) => $q->whereNull('client_id')->orWhereHas('client', fn ($c) => $c->where('status', '!=', 'inactive')))
            ->with(['client', 'executor'])
            ->orderBy('blocker_at')
            ->get();

        // ── "Minha semana" (modo Execução) ──
        // Segunda a sexta, cada tarefa minha (executor) na coluna da data de APROVAÇÃO — a
        // mesma régua da Semana de Produção do Painel, só que filtrada em mim e incluindo o
        // que já concluí na semana (pra pessoa ver o que já entregou, não só o que falta).
        // Sem arrastar: data de aprovação é decisão de quem distribui, não do executor.
        $weekOffset = max(-8, min(8, (int) $request->get('semana', 0)));
        $weekDays = [];
        $weekBeforeCount = $weekAfterCount = $weekNoDateCount = 0;
        if ($show('minha_semana')) {
            $monday = now()->startOfWeek(\Carbon\CarbonInterface::MONDAY)->addWeeks($weekOffset)->startOfDay();
            $friday = $monday->copy()->addDays(4);
            $statusOrder = array_flip(array_unique(array_merge(['ajuste_alteracao'], array_keys(Task::$statuses))));

            $weekTasks = $this->executorTasksQuery($userId)
                ->where('status', '!=', 'cancelado')
                ->whereDate('approval_date', '>=', $monday)
                ->whereDate('approval_date', '<=', $friday)
                ->with('client')
                ->get()
                ->sortBy(fn ($t) => ($t->status === 'concluido' ? 1000 : 0) + ($statusOrder[$t->status] ?? 99))
                ->groupBy(fn ($t) => $t->approval_date->toDateString());

            for ($d = $monday->copy(); $d->lte($friday); $d->addDay()) {
                $weekDays[] = ['data' => $d->copy(), 'tasks' => $weekTasks->get($d->toDateString(), collect())->values()];
            }

            $abertas = fn () => $this->executorTasksQuery($userId)->whereNotIn('status', ['concluido', 'cancelado']);
            $weekBeforeCount = $abertas()->whereDate('approval_date', '<', $monday)->count();
            $weekAfterCount  = $abertas()->whereDate('approval_date', '>', $friday)->count();
            $weekNoDateCount = $abertas()->whereNull('approval_date')->count();
        }

        // ── Placar de pontos de sprint (Execução) — ver SprintScoreboard ──
        $scoreboard = $show('placar') ? app(SprintScoreboard::class)->build((int) $userId) : null;

        // ── Cockpit de Planejamento (modo Planejamento) — ver PlanningCockpit ──
        $planning = $show('planejamento') ? app(PlanningCockpit::class)->build() : null;

        // ── Cockpit de Distribuição (modo Distribuição) — ver DistributionCockpit ──
        $distribution = $show('distribuicao')
            ? app(DistributionCockpit::class)->build((int) $userId, $weekOffset, (array) $request->get('carga', DistributionCockpit::DEFAULT_LOAD_STATUSES))
            : null;

        $today = today();
        $meetingsPosReuniao = $meetingsRealizadas = $clientsWithoutActivePlan = $plansExpiringSoon = $activePlans = collect();
        $openTickets = $roundsPending = $roundsApproved = $roundsChangesRequested = collect();
        $roundsAwaitingSendCount = 0;
        $headsTickets = collect();
        $pendingTasksCount = 0;
        $creativosProntos = $creativosProntosTasks = $budgetsNeedingAddition = $campaignsNeedingOptimization = collect();

        // ── Seção "Estratégia" ──
        if ($show('estrategia')) {
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
        }

        // ── Seção "Atendimento" ──
        if ($show('atendimento')) {
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
        }

        // ── Seção "Heads" (Criativa & Tech, mesma seção pros dois) ──
        // "Responsável" aqui é o papel dedicado na task_executors (pivot role =
        // 'responsavel'), diferente de "executor" — é quem responde pela qualidade,
        // não quem produz.
        if ($show('heads')) {
            $headsTickets = Task::where('is_ticket', true)
                ->whereNotIn('status', ['concluido', 'cancelado'])
                ->whereNull('sprint_id') // já triado pra uma Sprint = aparece só lá, não duplica aqui
                ->whereHas('responsibles', fn ($q) => $q->where('users.id', $userId))
                ->with('client')
                ->orderBy('due_date')
                ->limit(8)
                ->get();
        }

        // ── Pendências de cadastro (transversal, não é seção por papel) ──
        // Restrito a whereNull('sprint_id') de propósito, pra bater exatamente
        // com o que aparece na Fila quando o usuário clicar no link. Só o total
        // é exibido no dashboard (cardo com o número), sem listagem de amostra.
        if ($show('cadastro')) {
            $pendingTasksCount = Task::pendente()->whereNull('sprint_id')->count();
        }

        // ── Seção "Mídia Paga" (papel Tráfego) ──
        // Coluna 1: fila compartilhada — gerada pela automação "Notificar Tráfego" (ver
        // /automacoes) sempre que uma tarefa de Campanhas Patrocinadas chega em Despacho ou
        // Concluído. unique('source_id') porque o fan-out cria 1 linha por destinatário do
        // papel Tráfego — aqui é 1 card por tarefa, não por pessoa.
        if ($show('midia_paga')) {
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
        }

        return view('dashboard', compact(
            'literaryQuote',
            'activeSprint', 'sprintTotal', 'sprintDone', 'sprintProgress', 'sprintByStatus',
            'myAdjustmentTasks', 'myProductionTasks', 'myReadyForProductionTasks',
            'myExecutorSprintByStatus', 'myExecutorSprintTotal',
            'myMeetingsByStatus',
            'meetingsPosReuniao', 'meetingsRealizadas',
            'clientsWithoutActivePlan', 'plansExpiringSoon', 'activePlans',
            'openTickets',
            'roundsPending', 'roundsAwaitingSendCount', 'roundsApproved', 'roundsChangesRequested',
            'headsTickets', 'myReviewTasks',
            'pendingTasksCount',
            'creativosProntos', 'creativosProntosTasks', 'budgetsNeedingAddition', 'campaignsNeedingOptimization',
            'myNotifications',
            'mode', 'show', 'myMeetingsToday', 'myOverdueTasks', 'myBlockers',
            'availableModes', 'subjectRoles', 'subjectIsAdmin', 'viewingAs', 'teamMembers',
            'myExecutorSprintDone', 'myPointsTotal', 'myPointsDone',
            'weekDays', 'weekOffset', 'weekBeforeCount', 'weekAfterCount', 'weekNoDateCount',
            'distribution', 'subjectUserId', 'planning', 'scoreboard'
        ));
    }

    /**
     * ?ver_como={userId} — só admin/dono, só gente da própria organização, nunca a si mesmo.
     * Qualquer coisa fora disso cai silenciosamente na Dashboard normal de quem está logado.
     *
     * @return array{0: ?User, 1: ?OrganizationUser}
     */
    private function resolveViewingAs(Request $request): array
    {
        if (! $request->filled('ver_como') || ! in_array(app('currentOrgRole'), ['owner', 'admin'], true)) {
            return [null, null];
        }

        $pivot = rescue(fn () => OrganizationUser::where('organization_id', app('currentOrganization')->id)
            ->where('user_id', (int) $request->get('ver_como'))
            ->first(), null, false);

        if (! $pivot || $pivot->user_id === Auth::id()) {
            return [null, null];
        }

        return [User::find($pivot->user_id), $pivot];
    }

    // Quem a pessoa acompanha na grade da Distribuição (DistributionCockpit::team()). A própria
    // pessoa ajusta o dela; admin/dono também pode ajustar o de alguém pelo "Ver como" — é
    // configuração de visão, não ação sobre tarefas, então não fere o "só leitura".
    public function setDistributionTeam(Request $request)
    {
        $org = app('currentOrganization');
        $data = $request->validate([
            'for_user'   => ['nullable', 'integer'],
            'user_ids'   => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
            'automatic'  => ['nullable', 'boolean'],
        ]);

        $target = (int) ($data['for_user'] ?? Auth::id());
        abort_unless($target === Auth::id() || in_array(app('currentOrgRole'), ['owner', 'admin'], true), 403);

        $members = OrganizationUser::where('organization_id', $org->id)->pluck('user_id')->all();
        $team = $request->boolean('automatic')
            ? null
            : array_values(array_intersect(array_map('intval', $data['user_ids'] ?? []), $members));

        OrganizationUser::where('organization_id', $org->id)->where('user_id', $target)
            ->update(['distribution_team' => $team === null ? null : json_encode($team)]);

        return back()->with('success', $team === null ? 'Time da Distribuição voltou ao automático.' : 'Time da Distribuição atualizado.');
    }

    // Troca de modo pelo seletor do topo — salva como "último usado" (a pessoa volta
    // nele no próximo acesso) e leva pra Dashboard já no modo novo.
    public function setMode(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:' . implode(',', app('dashboardModes'))],
        ]);

        OrganizationUser::where('organization_id', app('currentOrganization')->id)
            ->where('user_id', Auth::id())
            ->update(['dashboard_mode' => $data['mode']]);

        return redirect()->route('dashboard');
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
        // whereHas('executors', ...) sem filtrar role pegava QUALQUER papel da tabela
        // pivot (responsável/observador inclusos) — "Operação" no Dashboard é
        // exclusivamente sobre o que a pessoa executa, não sobre onde ela é responsável.
        return Task::where(function ($q) use ($userId) {
            $q->where('executor_id', $userId)
              ->orWhereHas('executors', fn ($q2) => $q2->where('users.id', $userId)->where('task_executors.role', 'executor'));
        });
    }
}

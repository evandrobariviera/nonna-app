<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SprintController extends Controller
{
    /**
     * Regra de negócio: só pode existir 1 sprint ativa por vez. Futuramente isso vira
     * automático por data; por enquanto é travado manualmente aqui.
     */
    private function assertSingleActiveSprint(string $status, ?string $exceptSprintId = null): void
    {
        if ($status !== 'active') {
            return;
        }

        $existing = Sprint::where('status', 'active')
            ->when($exceptSprintId, fn ($q) => $q->where('id', '!=', $exceptSprintId))
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'status' => "Já existe uma sprint ativa: \"{$existing->title}\". Encerre ou reabra ela antes de ativar outra.",
            ]);
        }
    }

    public function index()
    {
        $sprints = Sprint::withCount('tasks')
            ->orderByDesc('starts_at')
            ->get();

        $active = $sprints->where('status', 'active')->first();

        return view('sprints.index', compact('sprints', 'active'));
    }

    public function create()
    {
        return view('sprints.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:150',
            'starts_at'  => 'required|date',
            'ends_at'    => 'required|date|after_or_equal:starts_at',
            'status'     => 'required|in:planning,active,closed',
        ]);

        $this->assertSingleActiveSprint($data['status']);

        Sprint::create([...$data, 'created_by' => Auth::id()]);

        return redirect()->route('sprints.index')->with('success', 'Sprint criada.');
    }

    public function show(Request $request, Sprint $sprint)
    {
        $sprint->load([
            'tasks.executor',
            'tasks.executors',
            'tasks.client',
            'tasks.project.macroPlan',
            'tasks.macroPlan',
            'tasks.meeting',
            'tasks.attachments',
            'tasks.statusTransitions',
        ]);

        // Board é a mesa de produção ativa — tarefa de cliente inativo nunca aparece
        // aqui (sem toggle; quem precisa ver isso usa a aba Lista, que tem o filtro).
        $activeClientTasks = $sprint->tasks->filter(fn ($t) => $t->client?->status !== 'inactive');

        // Agrupar por status (a coluna do board é o próprio status)
        $kanban = [];
        foreach (Task::$statuses as $statusKey => $meta) {
            $kanban[$statusKey] = $activeClientTasks
                ->where('status', $statusKey)
                ->values();
        }

        [$listTasks, $listGrouped, $listGroupBy] = $this->filteredListTasks($request, $sprint);
        $chartTasks = $this->applyCommonFilters($request, $sprint->tasks);
        [$sprintTasksByExecutor, $statusVolumeByDay] = $this->sprintLoadData($listTasks, $chartTasks, $sprint);
        [$weekDays, $weekKanban, $weekOutsideCount, $beforeWeekDate] = $this->weekBoardData($request, $sprint);

        // Backlog disponível para adicionar (sem sprint, status backlog) — cliente
        // inativo some daqui também, mesma regra da Fila (não faz sentido puxar
        // pra sprint uma tarefa de cliente que já saiu).
        $backlogTasks = Task::with(['client', 'project.macroPlan', 'macroPlan', 'meeting', 'executor'])
            ->whereNull('sprint_id')
            ->whereNotIn('status', ['cancelado', 'concluido'])
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->orderBy('due_date')
            ->get();

        $clients      = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
        // avatar_path/avatar_disk também, não só id+name — o dropdown de Responsável/Executor
        // (_person-fill.blade.php) precisa disso pra mostrar a foto certa assim que a pessoa é
        // escolhida (sem esperar reload), não só o avatar de quem já estava atribuído.
        $users        = User::orderBy('name')->get(['id', 'name', 'avatar_path', 'avatar_disk']);

        // Projetos pro dropdown "Vincular a projeto" (Board) e pro filtro de Projeto
        // (Lista) — só ativos, só de cliente ativo. Diferente da Fila, essa lista não
        // é re-filtrada por cliente selecionado (Board não recarrega via AJAX; a Lista
        // tem o próprio filtro client-side de Cliente→Projeto, igual à Fila).
        $projects     = Project::with('client:id,company_name,nickname')
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->orderBy('title')
            ->get(['id', 'title', 'client_id']);
        $sprints      = Sprint::whereIn('status', ['active', 'planning'])->orderByDesc('starts_at')->get();
        $activeSprint = $sprints->firstWhere('status', 'active');

        return view('sprints.show', compact(
            'sprint', 'kanban', 'listTasks', 'listGrouped', 'listGroupBy',
            'backlogTasks', 'clients', 'users', 'projects', 'sprints', 'activeSprint',
            'sprintTasksByExecutor', 'statusVolumeByDay',
            'weekDays', 'weekKanban', 'weekOutsideCount', 'beforeWeekDate'
        ));
    }

    // Fragmento da aba Lista — chamado via fetch por live-filter.js conforme o
    // usuário filtra, sem recarregar a página inteira (Board/Planejamento ficam intocados).
    public function listResults(Request $request, Sprint $sprint)
    {
        $sprint->load(['tasks.executor', 'tasks.executors', 'tasks.client', 'tasks.project.macroPlan', 'tasks.macroPlan', 'tasks.meeting', 'tasks.attachments', 'tasks.statusTransitions']);

        [$listTasks, $listGrouped, $listGroupBy] = $this->filteredListTasks($request, $sprint);
        $chartTasks = $this->applyCommonFilters($request, $sprint->tasks);
        [$sprintTasksByExecutor, $statusVolumeByDay] = $this->sprintLoadData($listTasks, $chartTasks, $sprint);

        $sprints      = Sprint::whereIn('status', ['active', 'planning'])->orderByDesc('starts_at')->get();
        $activeSprint = $sprints->firstWhere('status', 'active');
        // _person-fill.blade.php (dropdown de Responsável/Executor na linha) precisa de
        // $users — em show() isso já vinha do escopo da página inteira; aqui (fragmento
        // AJAX) precisa ser buscado também, senão o @foreach($users as $u) quebra a 500.
        $users        = User::orderBy('name')->get(['id', 'name', 'avatar_path', 'avatar_disk']);

        return view('sprints._list-results', compact(
            'listTasks', 'listGrouped', 'listGroupBy', 'sprints', 'activeSprint', 'users',
            'sprintTasksByExecutor', 'statusVolumeByDay'
        ));
    }

    // Filtros comuns entre a lista (aba "Lista") e o gráfico de "Carga da Sprint" — tudo
    // exceto o corte de concluído/cancelado, que cada chamador decide por conta (a lista
    // respeita o toggle "mostrar fechados"; o gráfico de volume por dia sempre inclui, ver
    // sprintLoadData()).
    private function applyCommonFilters(Request $request, \Illuminate\Support\Collection $tasks): \Illuminate\Support\Collection
    {
        if (!$request->boolean('list_mostrar_inativos')) {
            $tasks = $tasks->filter(fn ($t) => $t->client?->status !== 'inactive');
        }
        if ($request->filled('list_client_id')) {
            $tasks = $tasks->where('client_id', $request->get('list_client_id'));
        }
        if ($request->filled('list_project_id')) {
            $tasks = $tasks->where('project_id', $request->get('list_project_id'));
        }
        if ($request->filled('list_origin')) {
            $tasks = $tasks->where('origin', $request->get('list_origin'));
        }
        if ($request->filled('list_task_type')) {
            $tasks = $tasks->where('task_type', $request->get('list_task_type'));
        }
        if ($request->filled('list_status')) {
            $tasks = $tasks->where('status', $request->get('list_status'));
        }
        if ($request->filled('list_situacao')) {
            $tasks = $tasks->where('situation', $request->get('list_situacao'));
        }
        if ($request->filled('list_executor_id')) {
            $id = $request->get('list_executor_id');
            $tasks = $tasks->filter(function ($t) use ($id) {
                $execList = $t->executors->filter(fn ($u) => $u->pivot->role === 'executor');
                if ($execList->isEmpty() && $t->executor) {
                    $execList = collect([$t->executor]);
                }
                return $execList->contains('id', $id);
            });
        }
        if ($request->filled('list_responsavel_id')) {
            $id = $request->get('list_responsavel_id');
            $tasks = $tasks->filter(fn ($t) => $t->executors->contains(fn ($u) => $u->pivot->role === 'responsavel' && (string) $u->id === (string) $id));
        }
        if ($request->boolean('list_atrasadas')) {
            $tasks = $tasks->filter(fn ($t) => $t->isOverdue());
        }
        if ($request->boolean('list_pendencia')) {
            $tasks = $tasks->filter(fn ($t) => $t->isPendente());
        }
        if ($request->filled('list_search')) {
            $search = mb_strtolower($request->get('list_search'));
            $tasks = $tasks->filter(fn ($t) => str_contains(mb_strtolower($t->title), $search)
                || str_contains(mb_strtolower((string) $t->clickup_task_id), $search));
        }
        return $tasks->values();
    }

    // Lista filtrável (aba "Lista") — mesmo conjunto de tarefas da sprint, filtrado com
    // exatamente os mesmos filtros da Fila (ver partials/_task-filter-bar.blade.php).
    private function filteredListTasks(Request $request, Sprint $sprint): array
    {
        $listTasks = $sprint->tasks;
        if (!$request->boolean('list_mostrar_fechados')) {
            $listTasks = $listTasks->whereNotIn('status', ['concluido', 'cancelado']);
        }
        $listTasks = $this->applyCommonFilters($request, $listTasks);

        $listGroupBy = $request->get('list_group_by', 'cliente');
        $listGrouped = Task::groupCollection($listTasks, $listGroupBy)->sortByDesc->count();

        return [$listTasks, $listGrouped, $listGroupBy];
    }

    // Monta os dois dados do bloco "Carga da Sprint" (aba Lista, acima da tabela):
    // (a) tarefas agrupadas por executor (avatar + total + breakdown por status) a partir
    //     do conjunto já filtrado da lista — reage aos mesmos filtros da tabela abaixo dele;
    // (b) volume de tarefas por status ao longo dos dias, reconstruído ponto-no-tempo a
    //     partir de task_status_transitions — sempre inclui concluído/cancelado (senão o
    //     gráfico nunca mostraria entregas com "mostrar fechados" desligado, que é o padrão).
    private function sprintLoadData(\Illuminate\Support\Collection $listTasks, \Illuminate\Support\Collection $chartTasks, Sprint $sprint): array
    {
        $tasksByExecutor = $listTasks
            ->groupBy(function ($t) {
                $exec = $t->executors->first(fn ($u) => $u->pivot->role === 'executor') ?? $t->executor;
                return $exec?->id ?? '__sem_executor__';
            })
            ->map(function ($tasks) {
                $exec = $tasks->first()->executors->first(fn ($u) => $u->pivot->role === 'executor')
                     ?? $tasks->first()->executor;
                return [
                    'executor'     => $exec, // null = "Sem executor"
                    'total'        => $tasks->count(),
                    'statusCounts' => $tasks->groupBy('status')->map->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $start = $sprint->starts_at->copy()->startOfDay();
        $end   = min(today(), $sprint->ends_at->copy()->startOfDay());
        $days  = collect();
        if ($start->lte($end)) {
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $days->push($d->copy());
            }
        }

        $counts = []; // [dateString][status] => int
        foreach ($chartTasks as $task) {
            $transitions = $task->statusTransitions; // já ordenado por changed_at (relação)
            $ptr = 0;
            $n = $transitions->count();
            $current = null;
            foreach ($days as $day) {
                $cutoff = $day->copy()->endOfDay();
                while ($ptr < $n && $transitions[$ptr]->changed_at->lte($cutoff)) {
                    $current = $transitions[$ptr]->to_status;
                    $ptr++;
                }
                if ($current !== null) {
                    $dateKey = $day->toDateString();
                    $counts[$dateKey][$current] = ($counts[$dateKey][$current] ?? 0) + 1;
                }
            }
        }

        $statusVolumeByDay = $days->map(function ($day) use ($counts) {
            $dayCounts = $counts[$day->toDateString()] ?? [];
            return [
                'date'   => $day,
                'label'  => $day->translatedFormat('d/m'),
                'counts' => $dayCounts,
                'total'  => array_sum($dayCounts),
            ];
        });

        return [$tasksByExecutor, $statusVolumeByDay];
    }

    // Kanban semanal (aba "Semana") — colunas são "Atrasadas" (tudo com approval_date antes
    // de segunda — precisa ser puxado pra uma data de produção) + os dias úteis (seg-sex) da
    // semana ATUAL (não da janela da sprint). Cada card entra na coluna do dia da sua
    // approval_date; tarefa com approval_date depois de sexta, ou sem approval_date, não
    // aparece em nenhuma coluna (só conta no aviso informativo).
    //
    // Filtro de Status é cumulativo (várias em simultâneo) e usa um valor sentinela ('todos')
    // pro "sem filtro": o live-filter.js remove campos vazios/checkboxes desmarcados da query
    // antes do fetch (ver resources/js/live-filter.js), então não dá pra distinguir "usuário
    // ainda não mexeu" (deve cair no padrão Backlog + Ajuste/Alteração) de "usuário desmarcou
    // tudo" só pela ausência do parâmetro — daí "Todos" ser um checkbox próprio, não "nenhum
    // marcado".
    private function weekBoardData(Request $request, Sprint $sprint): array
    {
        $weekDays = collect();
        $monday   = now()->startOfWeek(\Carbon\Carbon::MONDAY);
        for ($d = $monday->copy(); $d->lte($monday->copy()->addDays(4)); $d->addDay()) {
            $weekDays->push($d->copy());
        }

        $tasks = $sprint->tasks->filter(fn ($t) => $t->client?->status !== 'inactive');

        $selectedStatuses = $request->has('week_status')
            ? array_filter((array) $request->get('week_status'))
            : ['backlog', 'ajuste_alteracao'];
        if (!empty($selectedStatuses) && !in_array('todos', $selectedStatuses, true)) {
            $tasks = $tasks->whereIn('status', $selectedStatuses);
        }

        if ($request->filled('week_client_id')) {
            $tasks = $tasks->where('client_id', $request->get('week_client_id'));
        }
        if ($request->filled('week_task_type')) {
            $tasks = $tasks->where('task_type', $request->get('week_task_type'));
        }
        if ($request->filled('week_executor_id')) {
            $id = $request->get('week_executor_id');
            $tasks = $tasks->filter(function ($t) use ($id) {
                $execList = $t->executors->filter(fn ($u) => $u->pivot->role === 'executor');
                if ($execList->isEmpty() && $t->executor) {
                    $execList = collect([$t->executor]);
                }
                return $execList->contains('id', $id);
            });
        }

        $totalFiltered = $tasks->count();

        $weekDateStrings = $weekDays->map->toDateString();
        $tasksBeforeWeek = $tasks->filter(fn ($t) => $t->approval_date && $t->approval_date->lt($monday));
        $tasksInWeek     = $tasks->filter(fn ($t) => $t->approval_date && $weekDateStrings->contains($t->approval_date->toDateString()));
        $grouped         = $tasksInWeek->groupBy(fn ($t) => $t->approval_date->toDateString());

        // Ordena cada coluna por prioridade (urgente > médio > normal — mesma ordem/default de
        // Task::$priorities) e, dentro da mesma prioridade, Ajuste/Alteração antes de Backlog
        // (pedido explícito); qualquer outro status entra depois, na ordem de Task::$statuses.
        $priorityOrder = array_flip(array_keys(Task::$priorities));
        $statusOrder   = array_flip(array_unique(array_merge(['ajuste_alteracao', 'backlog'], array_keys(Task::$statuses))));
        $sortColumn = function ($colTasks) use ($priorityOrder, $statusOrder) {
            return $colTasks
                ->sortBy(fn ($t) => ($priorityOrder[$t->priority ?? 'normal'] ?? count($priorityOrder)) * 100
                    + ($statusOrder[$t->status] ?? count($statusOrder)))
                ->values();
        };

        $weekKanban = [
            'atrasadas' => $sortColumn($tasksBeforeWeek),
        ];
        foreach ($weekDays as $day) {
            $weekKanban[$day->toDateString()] = $sortColumn($grouped->get($day->toDateString()) ?? collect());
        }

        // Data usada no PATCH se alguém arrastar um card PRA DENTRO de "Atrasadas" (domingo
        // anterior à segunda — só serve de âncora determinística, não é um caso de uso real).
        $beforeWeekDate = $monday->copy()->subDay();

        $weekOutsideCount = $totalFiltered - $tasksInWeek->count() - $tasksBeforeWeek->count();

        return [$weekDays, $weekKanban, $weekOutsideCount, $beforeWeekDate];
    }

    // Fragmento da aba Semana — chamado via fetch por live-filter.js, mesmo padrão de listResults().
    public function weekResults(Request $request, Sprint $sprint)
    {
        $sprint->load(['tasks.executor', 'tasks.executors', 'tasks.client', 'tasks.project.macroPlan', 'tasks.macroPlan', 'tasks.meeting']);

        [$weekDays, $weekKanban, $weekOutsideCount, $beforeWeekDate] = $this->weekBoardData($request, $sprint);

        return view('sprints._week-results', compact('weekDays', 'weekKanban', 'weekOutsideCount', 'beforeWeekDate', 'sprint'));
    }

    public function update(Request $request, Sprint $sprint)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:150',
            'starts_at' => 'required|date',
            'ends_at'   => 'required|date|after_or_equal:starts_at',
            'status'    => 'required|in:planning,active,closed',
        ]);

        $this->assertSingleActiveSprint($data['status'], $sprint->id);

        $sprint->update($data);

        return redirect()->route('sprints.show', $sprint)->with('success', 'Sprint atualizada.');
    }

    public function lock(Sprint $sprint)
    {
        $this->assertSingleActiveSprint('active', $sprint->id);

        $sprint->update([
            'locked_at' => now(),
            'locked_by' => Auth::id(),
            'status'    => 'active',
        ]);

        return redirect()->route('sprints.show', $sprint)->with('success', 'Sprint travada — em execução.');
    }

    public function unlock(Sprint $sprint)
    {
        $sprint->update([
            'locked_at' => null,
            'locked_by' => null,
            'status'    => 'planning',
        ]);

        return redirect()->route('sprints.show', $sprint)->with('success', 'Sprint reaberta para planejamento.');
    }

    // Regra de negócio: sprint só encerra sem tarefa pendente — força o time a decidir o
    // destino de cada tarefa (concluir, cancelar ou mover pra próxima sprint via
    // moveIncompleteToNext()) em vez de deixar tudo cair no Backlog automaticamente.
    public function close(Sprint $sprint)
    {
        $pendingCount = $sprint->tasks()->whereNotIn('status', ['concluido', 'cancelado'])->count();

        if ($pendingCount > 0) {
            return redirect()->route('sprints.show', $sprint)
                ->with('error', "Não é possível encerrar: {$pendingCount} tarefa(s) ainda não concluída(s). Mova as pendentes para a próxima Sprint antes de encerrar.");
        }

        $sprint->update(['status' => 'closed']);

        return redirect()->route('sprints.show', $sprint)->with('success', 'Sprint encerrada.');
    }

    // Escape hatch da regra acima: joga tudo que não terminou pra sprint em Planejamento
    // mais próxima (a de starts_at mais cedo) — de propósito não cai mais no Backlog
    // sozinho, precisa existir uma próxima sprint pronta pra receber.
    public function moveIncompleteToNext(Sprint $sprint)
    {
        $nextSprint = Sprint::where('status', 'planning')
            ->where('id', '!=', $sprint->id)
            ->orderBy('starts_at')
            ->first();

        if (!$nextSprint) {
            return redirect()->route('sprints.show', $sprint)
                ->with('error', 'Nenhuma próxima Sprint (em Planejamento) encontrada. Crie a próxima Sprint antes de mover as tarefas pendentes.');
        }

        // update() em massa (não each->update()) pula Observers de propósito — mesma regra
        // de bulk actions do resto do app (ver TaskController::bulkUpdate).
        $moved = $sprint->tasks()
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->update(['sprint_id' => $nextSprint->id]);

        return redirect()->route('sprints.show', $sprint)
            ->with('success', "{$moved} tarefa(s) movida(s) para a Sprint \"{$nextSprint->title}\".");
    }

    public function addTask(Sprint $sprint, Task $task)
    {
        abort_if($sprint->status === 'closed', 403, 'Sprint encerrada.');

        if ($task->isPendente()) {
            throw ValidationException::withMessages([
                'pendencia' => "Tarefa \"{$task->title}\" tem pendências de cadastro e não pode ser adicionada à sprint. Corrija-a antes de adicioná-la.",
            ]);
        }

        $task->update(['sprint_id' => $sprint->id]);

        return redirect()->route('sprints.show', $sprint)->with('success', 'Tarefa adicionada à sprint.');
    }

    public function removeTask(Sprint $sprint, Task $task)
    {
        abort_if($sprint->isLocked(), 403, 'Sprint travada. Desbloqueie antes de remover tarefas.');

        $task->update(['sprint_id' => null]);

        return redirect()->route('sprints.show', $sprint)->with('success', 'Tarefa removida da sprint.');
    }

    public function destroy(Sprint $sprint)
    {
        $sprint->tasks()->update(['sprint_id' => null]);
        $sprint->delete();

        return redirect()->route('sprints.index')->with('success', 'Sprint removida.');
    }
}

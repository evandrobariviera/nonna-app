<?php

namespace App\Services\Agent;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Catálogo de ferramentas do Agente Nonna. Cada ferramenta = definição (o que a IA
 * vê: nome, explicação, parâmetros) + execução (consulta o banco com as regras de
 * sempre — Tenantable já filtra pela organização da pessoa logada).
 *
 * Ferramentas de LEITURA respondem na hora. As de AÇÃO (propor_*) só validam e
 * devolvem uma proposta — quem cria de verdade é o AgentActionExecutor, depois
 * que a pessoa clica "Confirmar" no cartão.
 *
 * Pra acrescentar uma ferramenta: definição em definitions(), rótulo em LABELS e
 * um método com o mesmo nome em camelCase (ex.: listar_tarefas → listarTarefas).
 */
class AgentToolbox
{
    public const PROPOSAL_TOOLS = ['propor_tarefa', 'propor_reuniao'];

    // O que aparece na tela enquanto o agente trabalha ("Consultou …").
    public const LABELS = [
        'buscar_clientes' => 'Procurou clientes',
        'resumo_cliente'  => 'Leu o resumo do cliente',
        'listar_projetos' => 'Consultou projetos e campanhas',
        'listar_tarefas'  => 'Consultou tarefas',
        'detalhe_tarefa'  => 'Abriu uma tarefa',
        'listar_reunioes' => 'Consultou a agenda',
        'ler_ata'         => 'Leu a ATA de uma reunião',
        'propor_tarefa'   => 'Preparou uma tarefa',
        'propor_reuniao'  => 'Preparou uma reunião',
    ];

    private const OPEN_STATUSES = ['backlog', 'em_producao', 'revisao_interna', 'ajuste_alteracao', 'aprovacao', 'despacho_agendamento'];

    public function __construct(private User $user) {}

    /** Definições no formato "tools" da OpenAI. */
    public function definitions(): array
    {
        $fn = fn (string $name, string $description, array $properties = [], array $required = []) => [
            'type'     => 'function',
            'function' => [
                'name'        => $name,
                'description' => $description,
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => (object) $properties,
                    'required'   => $required,
                ],
            ],
        ];

        $taskStatusKeys = array_keys(Task::$statuses);

        return [
            $fn('buscar_clientes', 'Procura clientes pelo nome da empresa ou apelido interno. Use sempre que a pessoa citar um cliente pelo nome, para descobrir o client_id.', [
                'termo' => ['type' => 'string', 'description' => 'Parte do nome do cliente'],
                'incluir_inativos' => ['type' => 'boolean', 'description' => 'true para incluir clientes inativos (padrão: só ativos e leads)'],
            ], ['termo']),

            $fn('resumo_cliente', 'Panorama de um cliente: dados, briefing, serviços contratados, contatos, planejamento atual com projetos/campanhas, contagem de tarefas abertas e atrasadas, próximas e últimas reuniões.', [
                'client_id' => ['type' => 'string'],
            ], ['client_id']),

            $fn('listar_projetos', 'Lista projetos e campanhas de um cliente (com o planejamento de origem e quantas tarefas abertas têm). Use para achar o project_id ao criar tarefa dentro de um projeto/campanha.', [
                'client_id' => ['type' => 'string'],
                'incluir_concluidos' => ['type' => 'boolean'],
            ], ['client_id']),

            $fn('listar_tarefas', 'Lista tarefas com filtros. Sem filtro de status traz só as abertas (não concluídas/canceladas). Ordenadas pelo prazo.', [
                'client_id'        => ['type' => 'string'],
                'project_id'       => ['type' => 'string'],
                'executor_user_id' => ['type' => 'integer', 'description' => 'Tarefas em que essa pessoa é executora'],
                'status'           => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $taskStatusKeys]],
                'somente_atrasadas'=> ['type' => 'boolean'],
                'termo'            => ['type' => 'string', 'description' => 'Palavra no título'],
                'limite'           => ['type' => 'integer', 'description' => 'Padrão 30, máximo 60'],
            ]),

            $fn('detalhe_tarefa', 'Abre uma tarefa: descrição completa, pessoas, datas e os últimos comentários.', [
                'task_id' => ['type' => 'string'],
            ], ['task_id']),

            $fn('listar_reunioes', 'Lista reuniões por período, cliente e/ou pessoas da equipe (organizador ou participante). Use também para checar agenda/choque de horário.', [
                'client_id' => ['type' => 'string'],
                'user_ids'  => ['type' => 'array', 'items' => ['type' => 'integer']],
                'de'        => ['type' => 'string', 'description' => 'AAAA-MM-DD (padrão: hoje)'],
                'ate'       => ['type' => 'string', 'description' => 'AAAA-MM-DD (padrão: 30 dias depois de "de")'],
                'limite'    => ['type' => 'integer', 'description' => 'Padrão 20, máximo 50'],
            ]),

            $fn('ler_ata', 'Lê a pauta, a ATA e os próximos passos de uma reunião.', [
                'meeting_id' => ['type' => 'string'],
            ], ['meeting_id']),

            $fn('propor_tarefa', 'Prepara uma tarefa para a pessoa confirmar na tela (NÃO cria sozinha). Sem project_id vira um chamado avulso do cliente.', [
                'client_id'           => ['type' => 'string'],
                'title'               => ['type' => 'string'],
                'description'         => ['type' => 'string'],
                'task_type'           => ['type' => 'string', 'enum' => array_keys(Task::$types)],
                'priority'            => ['type' => 'string', 'enum' => array_keys(Task::$priorities)],
                'due_date'            => ['type' => 'string', 'description' => 'Prazo AAAA-MM-DD'],
                'executor_user_id'    => ['type' => 'integer'],
                'responsavel_user_id' => ['type' => 'integer'],
                'project_id'          => ['type' => 'string', 'description' => 'Projeto/campanha do mesmo cliente (opcional)'],
            ], ['client_id', 'title', 'task_type']),

            $fn('propor_reuniao', 'Prepara uma reunião para a pessoa confirmar na tela (NÃO agenda sozinha).', [
                'title'                => ['type' => 'string'],
                'type'                 => ['type' => 'string', 'enum' => array_keys(Meeting::$types)],
                'modality'             => ['type' => 'string', 'enum' => array_keys(Meeting::$modalities)],
                'scheduled_at'         => ['type' => 'string', 'description' => 'AAAA-MM-DD HH:MM (horário de Brasília)'],
                'duration_minutes'     => ['type' => 'integer'],
                'client_id'            => ['type' => 'string'],
                'organized_by_user_id' => ['type' => 'integer', 'description' => 'Padrão: a pessoa da conversa'],
                'participant_user_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'agenda'               => ['type' => 'string', 'description' => 'Pauta em texto simples'],
                'location'             => ['type' => 'string'],
                'online_link'          => ['type' => 'string'],
            ], ['title', 'type', 'scheduled_at']),
        ];
    }

    /**
     * Executa uma ferramenta. Leitura: ['result' => array]. Ação: ['result' => array,
     * 'proposal' => array] — ou só 'result' com 'erro' quando a validação falha
     * (a IA lê o erro e corrige).
     */
    public function call(string $name, array $args): array
    {
        $method = Str::camel($name);
        if (!array_key_exists($name, self::LABELS) || !method_exists($this, $method)) {
            return ['result' => ['erro' => "Ferramenta desconhecida: {$name}"]];
        }

        // A IA costuma mandar opcionais "vazios" ("" ou 0 em *_id) em vez de omitir —
        // tratar como não informado (senão "" vira data inválida no banco, 0 vira pessoa inexistente).
        $args = array_filter($args, fn ($v, $k) => $v !== null && $v !== '' && $v !== []
            && !(str_ends_with($k, '_id') && ($v === 0 || $v === '0')), ARRAY_FILTER_USE_BOTH);

        try {
            $out = $this->{$method}($args);
        } catch (\Throwable $e) {
            report($e);
            return ['result' => ['erro' => 'Falha ao consultar: ' . Str::limit($e->getMessage(), 200)]];
        }

        return in_array($name, self::PROPOSAL_TOOLS, true) ? $out : ['result' => $out];
    }

    // ── Leitura ─────────────────────────────────────────────────────────────

    private function buscarClientes(array $a): array
    {
        $term = trim((string) ($a['termo'] ?? ''));
        if ($term === '') {
            return ['erro' => 'Informe parte do nome.'];
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        // "WineRun" acha "Wine Run" (e vice-versa): compara também sem espaços.
        $compact = '%' . str_replace(['%', '_', ' '], ['\%', '\_', ''], $term) . '%';
        $clients = Client::query()
            ->where(fn ($q) => $q->where('company_name', 'ilike', $like)->orWhere('nickname', 'ilike', $like)
                ->orWhereRaw("replace(company_name, ' ', '') ilike ?", [$compact])
                ->orWhereRaw("replace(coalesce(nickname, ''), ' ', '') ilike ?", [$compact]))
            ->when(empty($a['incluir_inativos']), fn ($q) => $q->where('status', '!=', 'inactive'))
            ->orderBy('company_name')
            ->limit(10)
            ->get(['id', 'company_name', 'nickname', 'status', 'segment']);

        return [
            'encontrados' => $clients->count(),
            'clientes'    => $clients->map(fn ($c) => [
                'client_id' => $c->id,
                'nome'      => $c->company_name,
                'apelido'   => $c->nickname,
                'status'    => Client::$statuses[$c->status]['label'] ?? $c->status,
                'segmento'  => $c->segment,
                'link'      => $this->link('clients.show', $c),
            ])->all(),
        ];
    }

    private function resumoCliente(array $a): array
    {
        $client = $this->findClient($a['client_id'] ?? null);
        if (!$client) {
            return ['erro' => 'Cliente não encontrado.'];
        }

        $client->load(['contacts:id,name,job_title,email,whatsapp', 'creativeLead:id,name', 'adAccounts:id,client_id,platform,status']);

        $openTasks = Task::where('client_id', $client->id)->whereIn('status', self::OPEN_STATUSES);
        $byStatus = (clone $openTasks)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $overdue  = (clone $openTasks)->whereNotNull('due_date')->where('due_date', '<', today())->count();

        $plans = MacroPlan::where('client_id', $client->id)
            ->orderByDesc('period_start')->orderByDesc('created_at')
            ->limit(2)
            ->get(['id', 'title', 'status', 'period_start', 'period_end']);

        $currentPlan = $plans->first();
        $projects = $currentPlan
            ? Project::where('macro_plan_id', $currentPlan->id)->orderBy('position')
                ->get(['id', 'macro_plan_id', 'title', 'type', 'status', 'start_date', 'end_date'])
            : collect();

        $upcoming = Meeting::where('client_id', $client->id)->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')->limit(5)->get(['id', 'title', 'type', 'status', 'scheduled_at']);
        $past = Meeting::where('client_id', $client->id)->where('scheduled_at', '<', now())
            ->orderByDesc('scheduled_at')->limit(5)->get(['id', 'title', 'type', 'status', 'scheduled_at', 'ata']);

        return [
            'client_id'   => $client->id,
            'nome'        => $client->company_name,
            'apelido'     => $client->nickname,
            'status'      => Client::$statuses[$client->status]['label'] ?? $client->status,
            'segmento'    => $client->segment,
            'site'        => $client->website,
            'servicos_contratados' => $client->contracted_services,
            'verba_mensal_anuncios' => $client->monthly_ad_budget,
            'lider_criativo' => $client->creativeLead?->name,
            'briefing'    => $this->plain($client->briefing, 3000),
            'observacoes_internas' => $this->plain($client->notes, 1500),
            'link'        => $this->link('clients.show', $client),
            'contatos'    => $client->contacts->map(fn ($c) => array_filter([
                'nome' => $c->name, 'cargo' => $c->job_title, 'email' => $c->email, 'whatsapp' => $c->whatsapp,
            ]))->values()->all(),
            'contas_de_anuncio' => $client->adAccounts->map(fn ($acc) => "{$acc->platform} ({$acc->status})")->values()->all(),
            'planejamentos' => $plans->map(fn ($p) => [
                'titulo'  => $p->title,
                'status'  => MacroPlan::$statuses[$p->status]['label'] ?? $p->status,
                'periodo' => $this->period($p->period_start, $p->period_end),
            ])->all(),
            'projetos_do_planejamento_atual' => $projects->map(fn ($p) => $this->projectRow($p))->all(),
            'tarefas_abertas' => [
                'total'      => $byStatus->sum(),
                'por_status' => $byStatus->mapWithKeys(fn ($n, $s) => [Task::$statuses[$s]['label'] ?? $s => $n])->all(),
                'atrasadas'  => $overdue,
            ],
            'proximas_reunioes' => $upcoming->map(fn ($m) => $this->meetingRow($m))->all(),
            'ultimas_reunioes'  => $past->map(fn ($m) => $this->meetingRow($m) + ['tem_ata' => trim(strip_tags((string) $m->ata)) !== ''])->all(),
        ];
    }

    private function listarProjetos(array $a): array
    {
        $client = $this->findClient($a['client_id'] ?? null);
        if (!$client) {
            return ['erro' => 'Cliente não encontrado.'];
        }

        $projects = Project::where('client_id', $client->id)
            ->when(empty($a['incluir_concluidos']), fn ($q) => $q->whereNotIn('status', ['concluido', 'cancelado']))
            ->with('macroPlan:id,title')
            ->withCount(['tasks as tarefas_abertas' => fn ($q) => $q->whereIn('status', self::OPEN_STATUSES)])
            ->orderByDesc('created_at')
            ->limit(40)
            ->get(['id', 'macro_plan_id', 'client_id', 'title', 'type', 'status', 'start_date', 'end_date', 'objective']);

        return [
            'cliente'  => $client->company_name,
            'projetos' => $projects->map(fn ($p) => $this->projectRow($p) + [
                'planejamento'    => $p->macroPlan?->title,
                'objetivo'        => $this->plain($p->objective, 300),
                'tarefas_abertas' => $p->tarefas_abertas,
            ])->all(),
        ];
    }

    private function listarTarefas(array $a): array
    {
        $limit = min(max((int) ($a['limite'] ?? 30), 1), 60);
        $statuses = array_values(array_intersect((array) ($a['status'] ?? []), array_keys(Task::$statuses)));

        $query = Task::query()
            ->with(['client:id,company_name', 'project:id,title', 'executors:id,name', 'responsibles:id,name'])
            ->when(!empty($a['client_id']), fn ($q) => $q->where('client_id', $a['client_id']))
            ->when(!empty($a['project_id']), fn ($q) => $q->where('project_id', $a['project_id']))
            ->when(!empty($a['executor_user_id']), fn ($q) => $q->whereHas('executors', fn ($e) => $e->where('users.id', (int) $a['executor_user_id'])))
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses), fn ($q) => $q->whereIn('status', self::OPEN_STATUSES))
            ->when(!empty($a['somente_atrasadas']), fn ($q) => $q->whereNotNull('due_date')->where('due_date', '<', today()))
            ->when(!empty($a['termo']), fn ($q) => $q->where('title', 'ilike', '%' . str_replace(['%', '_'], ['\%', '\_'], $a['termo']) . '%'));

        $total = (clone $query)->count();
        $tasks = $query->orderByRaw('due_date is null')->orderBy('due_date')->limit($limit)->get();

        return [
            'total_encontrado' => $total,
            'mostrando'        => $tasks->count(),
            'tarefas'          => $tasks->map(fn (Task $t) => $this->taskRow($t))->all(),
        ];
    }

    private function detalheTarefa(array $a): array
    {
        $task = Task::with(['client:id,company_name', 'project:id,title', 'macroPlan:id,title', 'executors:id,name', 'responsibles:id,name',
                'comments' => fn ($q) => $q->with('user:id,name')->latest()->limit(10)])
            ->find($a['task_id'] ?? null);
        if (!$task) {
            return ['erro' => 'Tarefa não encontrada.'];
        }

        return $this->taskRow($task) + [
            'planejamento'   => $task->macroPlan?->title,
            'destino'        => $task->destinationLabel() ?: null,
            'data_aprovacao' => $task->approval_date?->format('d/m/Y'),
            'data_publicacao'=> $task->publish_date?->format('d/m/Y'),
            'descricao'      => $this->plain($task->description, 4000),
            'ultimos_comentarios' => $task->comments->map(fn ($c) => [
                'quem'  => $c->user?->name ?? 'Cliente',
                'quando'=> $c->created_at?->format('d/m H:i'),
                'texto' => $this->plain($c->body, 600),
            ])->all(),
        ];
    }

    private function listarReunioes(array $a): array
    {
        $from  = $this->date($a['de'] ?? null) ?? today();
        $until = $this->date($a['ate'] ?? null) ?? $from->copy()->addDays(30);
        $limit = min(max((int) ($a['limite'] ?? 20), 1), 50);
        $userIds = array_map('intval', (array) ($a['user_ids'] ?? []));

        $meetings = Meeting::query()
            ->with(['client:id,company_name', 'organizer:id,name', 'participants:id,name'])
            ->whereBetween('scheduled_at', [$from->copy()->startOfDay(), $until->copy()->endOfDay()])
            ->when(!empty($a['client_id']), fn ($q) => $q->where('client_id', $a['client_id']))
            ->when($userIds, fn ($q) => $q->where(fn ($w) => $w->whereIn('organized_by', $userIds)
                ->orWhereHas('participants', fn ($p) => $p->whereIn('users.id', $userIds))))
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        return [
            'periodo'  => $from->format('d/m/Y') . ' a ' . $until->format('d/m/Y'),
            'reunioes' => $meetings->map(fn (Meeting $m) => $this->meetingRow($m) + [
                'duracao_min'  => $m->duration_minutes,
                'cliente'      => $m->client?->company_name,
                'organizador'  => $m->organizer?->name,
                'participantes'=> $m->participants->pluck('name')->all(),
            ])->all(),
        ];
    }

    private function lerAta(array $a): array
    {
        $m = Meeting::with('client:id,company_name')->find($a['meeting_id'] ?? null);
        if (!$m) {
            return ['erro' => 'Reunião não encontrada.'];
        }

        return $this->meetingRow($m) + [
            'cliente'         => $m->client?->company_name,
            'pauta'           => $this->plain($m->agenda, 3000),
            'ata'             => $this->plain($m->ata, 12000) ?: 'Sem ATA registrada.',
            'proximos_passos' => $this->plain(is_array($m->next_steps) ? json_encode($m->next_steps, JSON_UNESCAPED_UNICODE) : $m->next_steps, 2000),
        ];
    }

    // ── Ações (só propõem) ──────────────────────────────────────────────────

    private function proporTarefa(array $a): array
    {
        $v = Validator::make($a, [
            'client_id'           => 'required|uuid',
            'title'               => 'required|string|max:300',
            'description'         => 'nullable|string|max:10000',
            'task_type'           => 'required|in:' . implode(',', array_keys(Task::$types)),
            'priority'            => 'nullable|in:' . implode(',', array_keys(Task::$priorities)),
            'due_date'            => 'nullable|date_format:Y-m-d',
            'executor_user_id'    => 'nullable|integer',
            'responsavel_user_id' => 'nullable|integer',
            'project_id'          => 'nullable|uuid',
        ]);
        if ($v->fails()) {
            return ['result' => ['erro' => 'Dados inválidos: ' . implode(' ', $v->errors()->all())]];
        }
        $data = $v->validated();

        $client = $this->findClient($data['client_id']);
        if (!$client) {
            return ['result' => ['erro' => 'Cliente não encontrado.']];
        }

        $project = null;
        if (!empty($data['project_id'])) {
            $project = Project::find($data['project_id']);
            if (!$project || $project->client_id !== $client->id) {
                return ['result' => ['erro' => 'Esse projeto/campanha não é deste cliente. Use listar_projetos.']];
            }
        }

        $team = $this->team();
        foreach (['executor_user_id' => 'Executor', 'responsavel_user_id' => 'Responsável'] as $field => $label) {
            if (!empty($data[$field]) && !$team->has($data[$field])) {
                return ['result' => ['erro' => "{$label}: pessoa não está na equipe. Use só ids da lista da equipe."]];
            }
        }

        $display = array_filter([
            ['Cliente', $client->company_name],
            $project ? ['Projeto/Campanha', $project->title] : ['Onde', 'Chamado avulso do cliente'],
            ['Título', $data['title']],
            ['Tipo', Task::$types[$data['task_type']]],
            ['Prioridade', Task::$priorities[$data['priority'] ?? 'normal']['label']],
            !empty($data['due_date']) ? ['Prazo', Carbon::parse($data['due_date'])->format('d/m/Y')] : null,
            !empty($data['executor_user_id']) ? ['Executor', $team[$data['executor_user_id']]] : null,
            !empty($data['responsavel_user_id']) ? ['Responsável', $team[$data['responsavel_user_id']]] : null,
            !empty($data['description']) ? ['Descrição', $data['description']] : null,
        ]);

        return [
            'proposal' => ['type' => 'task', 'title' => 'Nova tarefa', 'data' => $data, 'display' => array_values($display)],
            'result'   => ['ok' => 'Cartão da tarefa mostrado na tela. A pessoa precisa clicar em "Confirmar" — ainda NÃO foi criada.'],
        ];
    }

    private function proporReuniao(array $a): array
    {
        $v = Validator::make($a, [
            'title'                  => 'required|string|max:200',
            'type'                   => 'required|in:' . implode(',', array_keys(Meeting::$types)),
            'modality'               => 'nullable|in:' . implode(',', array_keys(Meeting::$modalities)),
            'scheduled_at'           => 'required|date_format:Y-m-d H:i',
            'duration_minutes'       => 'nullable|integer|min:5|max:480',
            'client_id'              => 'nullable|uuid',
            'organized_by_user_id'   => 'nullable|integer',
            'participant_user_ids'   => 'nullable|array|max:30',
            'participant_user_ids.*' => 'integer',
            'agenda'                 => 'nullable|string|max:5000',
            'location'               => 'nullable|string|max:255',
            'online_link'            => 'nullable|url|max:500',
        ]);
        if ($v->fails()) {
            return ['result' => ['erro' => 'Dados inválidos: ' . implode(' ', $v->errors()->all())]];
        }
        $data = $v->validated();
        $data['modality'] ??= 'online';
        $data['duration_minutes'] ??= 60;
        $data['organized_by_user_id'] ??= $this->user->id;
        $data['participant_user_ids'] = array_values(array_unique($data['participant_user_ids'] ?? []));

        $client = null;
        if (!empty($data['client_id'])) {
            $client = $this->findClient($data['client_id']);
            if (!$client) {
                return ['result' => ['erro' => 'Cliente não encontrado.']];
            }
        }

        $team = $this->team();
        foreach (array_merge([$data['organized_by_user_id']], $data['participant_user_ids']) as $id) {
            if (!$team->has($id)) {
                return ['result' => ['erro' => "Pessoa {$id} não está na equipe. Use só ids da lista da equipe."]];
            }
        }

        $when = Carbon::createFromFormat('Y-m-d H:i', $data['scheduled_at'], 'America/Sao_Paulo');
        if ($when->isPast()) {
            return ['result' => ['erro' => 'Essa data/hora já passou. Confirme a data com a pessoa.']];
        }

        $display = array_filter([
            ['Título', $data['title']],
            ['Tipo', Meeting::$types[$data['type']]],
            ['Quando', $when->locale('pt_BR')->isoFormat('ddd, DD/MM [às] HH:mm') . " ({$data['duration_minutes']} min)"],
            ['Modalidade', Meeting::$modalities[$data['modality']]],
            $client ? ['Cliente', $client->company_name] : null,
            ['Organizador', $team[$data['organized_by_user_id']]],
            $data['participant_user_ids'] ? ['Participantes', collect($data['participant_user_ids'])->map(fn ($id) => $team[$id])->implode(', ')] : null,
            !empty($data['location']) ? ['Local', $data['location']] : null,
            !empty($data['online_link']) ? ['Link', $data['online_link']] : null,
            !empty($data['agenda']) ? ['Pauta', $data['agenda']] : null,
        ]);

        return [
            'proposal' => ['type' => 'meeting', 'title' => 'Nova reunião', 'data' => $data, 'display' => array_values($display)],
            'result'   => ['ok' => 'Cartão da reunião mostrado na tela. A pessoa precisa clicar em "Confirmar" — ainda NÃO foi agendada.'],
        ];
    }

    // ── Apoio ───────────────────────────────────────────────────────────────

    /** Equipe interna (id => nome) — mesma régua do ContextResolver::teamCatalog(). */
    public function team(): \Illuminate\Support\Collection
    {
        return once(fn () => User::whereNull('client_id')->orderBy('name')->pluck('name', 'id'));
    }

    private function findClient(?string $id): ?Client
    {
        return $id && Str::isUuid($id) ? Client::find($id) : null;
    }

    private function taskRow(Task $t): array
    {
        return array_filter([
            'task_id'     => $t->id,
            'titulo'      => $t->title,
            'link'        => $this->link('tasks.show', $t),
            'cliente'     => $t->client?->company_name,
            'projeto'     => $t->project?->title,
            'tipo'        => $t->typeLabel(),
            'status'      => $t->statusLabel(),
            'situacao'    => $t->situation ?: null,
            'prioridade'  => $t->priorityLabel(),
            'prazo'       => $t->due_date?->format('d/m/Y'),
            'atrasada'    => $t->isOverdue() ?: null,
            'executores'  => $t->executors->pluck('name')->implode(', ') ?: null,
            'responsavel' => $t->responsibles->pluck('name')->implode(', ') ?: null,
            'chamado'     => $t->is_ticket ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function projectRow(Project $p): array
    {
        return array_filter([
            'project_id' => $p->id,
            'titulo'     => $p->title,
            'tipo'       => Project::$types[$p->type]['label'] ?? $p->type,
            'status'     => Project::$statuses[$p->status]['label'] ?? $p->status,
            'periodo'    => $this->period($p->start_date, $p->end_date),
            'link'       => $this->link('projects.showDirect', $p),
        ]);
    }

    private function meetingRow(Meeting $m): array
    {
        return [
            'meeting_id' => $m->id,
            'titulo'     => $m->title,
            'tipo'       => Meeting::$types[$m->type] ?? $m->type,
            'status'     => Meeting::$statuses[$m->status]['label'] ?? $m->status,
            'quando'     => $m->scheduled_at?->locale('pt_BR')->isoFormat('ddd, DD/MM/YYYY HH:mm'),
            'link'       => $this->link('meetings.show', $m),
        ];
    }

    private function link(string $route, $model): ?string
    {
        return Route::has($route) ? route($route, $model) : null;
    }

    private function plain($text, int $max): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }
        $text = is_array($text) ? json_encode($text, JSON_UNESCAPED_UNICODE) : (string) $text;
        $text = preg_replace('/<\s*(br|\/p|\/li|\/h\d)\s*\/?>/i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text)));

        return $text === '' ? null : Str::limit($text, $max);
    }

    private function period($start, $end): ?string
    {
        $s = $start ? Carbon::parse($start)->format('d/m/Y') : null;
        $e = $end ? Carbon::parse($end)->format('d/m/Y') : null;

        return $s || $e ? trim(($s ?? '?') . ' a ' . ($e ?? '?')) : null;
    }

    private function date(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m-d', $value)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

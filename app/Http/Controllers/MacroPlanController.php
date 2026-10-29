<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\Meeting;
use App\Models\User;
use App\Services\MacroPlanDocumentService;
use App\Services\Projects\ProjectDiscussionMeetingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MacroPlanController extends Controller
{
    // Lista global de todos os macroplanejamentos
    public function index(Request $request)
    {
        $macroplans = $this->filteredMacroplans($request);
        $clients    = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);

        return view('macroplans.index', compact('macroplans', 'clients'));
    }

    // Fragmento da listagem — chamado via fetch por live-filter.js conforme o
    // usuário filtra, sem recarregar a página inteira.
    public function results(Request $request)
    {
        $macroplans = $this->filteredMacroplans($request);

        return view('macroplans._results', compact('macroplans'));
    }

    private function filteredMacroplans(Request $request)
    {
        $query = MacroPlan::with(['client', 'responsible'])
            ->orderByDesc('period_start');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // Oculta encerrados por padrão — a menos que o usuário filtre por status específico ou ative o toggle
        if (!$request->boolean('mostrar_concluidos') && !$request->filled('status')) {
            $query->where('status', '!=', 'concluido');
        }

        // Cliente inativo some por padrão — nada é alterado no cliente/planejamento,
        // só escondido daqui (reversível: cliente reativou, volta a aparecer).
        if (!$request->boolean('mostrar_inativos')) {
            $query->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'));
        }

        return $query->paginate(20)->withQueryString();
    }

    public function create(Request $request)
    {
        $client  = $request->filled('client_id')
            ? Client::findOrFail($request->client_id)
            : null;
        $clients = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
        $users   = User::orderBy('name')->get(['id', 'name']);

        // Ciclo fechado: quem já tem planejamento ativo (fora da Renovação) ou um em elaboração
        // aparece bloqueado no seletor, com o motivo. Uma consulta pra todos os clientes.
        $openByClient = MacroPlan::where('status', '!=', 'concluido')->get()->groupBy('client_id');
        $blocked = $clients->mapWithKeys(fn ($c) => [
            $c->id => MacroPlan::creationBlockReason($c->id, $openByClient->get($c->id, collect())),
        ])->filter();

        return view('macroplans.create', compact('client', 'clients', 'users', 'blocked'));
    }

    public function store(Request $request, MacroPlanDocumentService $documents)
    {
        // Com o HTML da skill o período é opcional (o documento traz); sem, é obrigatório.
        $hasDocument = $request->hasFile('document');
        $data = $request->validate([
            'client_id'      => 'required|uuid|exists:clients,id',
            'focus'          => 'nullable|string|max:200',
            'period_start'   => ($hasDocument ? 'nullable' : 'required') . '|date',
            'period_end'     => ($hasDocument ? 'nullable' : 'required') . '|date|after:period_start',
            'responsible_id' => 'nullable|exists:users,id',
            'status'         => 'required|in:' . implode(',', array_keys(MacroPlan::$statuses)),
            'document'       => 'nullable|file|mimes:html,htm|max:5120',
        ]);
        unset($data['document']);

        // Ciclo fechado: um ativo por cliente; o próximo só na Renovação do atual.
        if ($reason = MacroPlan::creationBlockReason($data['client_id'])) {
            return back()->withInput()->withErrors(['client_id' => $reason]);
        }

        if (!$hasDocument) {
            $plan = MacroPlan::create([
                ...$data,
                'created_by' => Auth::id(),
            ]);

            return redirect()->route('macroplans.edit', $plan)
                ->with('success', 'Planejamento criado. Envie o documento (HTML) quando estiver pronto.');
        }

        try {
            $parsed = $documents->parse($request->file('document'));
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['document' => $e->getMessage()]);
        }

        // Formulário manda; o que ficou em branco vem do documento.
        $data['period_start'] = $data['period_start'] ?: ($parsed['period_start'] ?? null);
        $data['period_end']   = $data['period_end'] ?: ($parsed['period_end'] ?? null);
        if (!$data['period_start'] || !$data['period_end']) {
            return back()->withInput()->withErrors(['period_start' => 'Informe o início e o fim do período — o documento não trouxe essas datas.']);
        }
        if ($data['period_end'] <= $data['period_start']) {
            return back()->withInput()->withErrors(['period_end' => 'O fim do período precisa ser depois do início.']);
        }

        [$plan, $warnings] = $documents->createFromDocument(
            Client::findOrFail($data['client_id']), $data, $parsed, $request->file('document')
        );

        $count = count($parsed['projects'] ?? []);

        return redirect()->route('macroplans.edit', $plan)
            ->with('success', "Planejamento criado a partir do documento: {$count} campanha(s)/projeto(s).")
            ->with('import_warnings', $warnings);
    }

    /**
     * Preview leve para o painel lateral (canvas) — não a página completa.
     */
    public function preview(MacroPlan $macroplan)
    {
        $macroplan->load(['client', 'responsible', 'projects']);

        return view('macroplans._preview', compact('macroplan'));
    }

    public function edit(MacroPlan $macroplan, Request $request)
    {
        $macroplan->load([
            'client.creativeLead:id,name,avatar_path,avatar_disk', 'responsible', 'projects.tasks', 'projects.phases', 'projects.members:users.id,users.name,users.avatar_path,users.avatar_disk',
            'projects.discussionMeetings:meetings.id,meetings.title,meetings.status,meetings.scheduled_at', 'attachments.uploadedBy', 'activities.user', 'comments.user:id,name,avatar_path,avatar_disk',
            'tasks' => fn ($q) => $q->whereNull('project_id')->orderByDesc('created_at'),
        ]);
        $users          = User::orderBy('name')->get(['id', 'name', 'avatar_path', 'avatar_disk']);
        // Planejamento enxuto (2026-10): abre no Documento (HTML). Blocos antigos só entram
        // na navegação quando têm conteúdo, e em modo leitura.
        $legacyBlocks = $macroplan->legacyBlocks();
        $mainDocument = $macroplan->mainDocument();
        $currentBlock = $request->get('bloco', 'documento');

        if (!in_array($currentBlock, array_merge(['documento', 'linha-do-tempo', 'bloco3', 'discussao', 'pendencias', 'meta'], $legacyBlocks), true)) {
            $currentBlock = 'documento';
        }

        $discussionItems = ProjectDiscussionMeetingService::formItems($macroplan);
        // Ligadas direto, citadas na pauta, ou citadas por uma campanha/tarefa do planejamento.
        $relatedMeetings = Meeting::relatedToMacroPlan($macroplan)
            ->with(['agendaMacroPlans:macro_plans.id', 'agendaProjects:projects.id,projects.title,projects.type,projects.macro_plan_id', 'agendaTasks:tasks.id,tasks.title,tasks.project_id,tasks.macro_plan_id'])
            ->orderByDesc('scheduled_at')
            ->get();

        // Etapas do ciclo + campanhas/projetos na régua (usa projects.phases/tasks já carregados).
        $planTimeline = \App\Services\Projects\MacroPlanTimeline::for($macroplan);

        // Quem a discussão avisa — aparece embaixo do campo de comentário.
        $discussionRecipients = User::whereIn('id', $macroplan->discussionRecipientIds())->orderBy('name')->pluck('name');

        return view('macroplans.edit', compact('macroplan', 'users', 'currentBlock', 'legacyBlocks', 'mainDocument', 'discussionItems', 'relatedMeetings', 'planTimeline', 'discussionRecipients'));
    }

    public function update(Request $request, MacroPlan $macroplan)
    {
        $block = $request->input('_block');

        // Atualizar metadados gerais
        if ($block === 'meta') {
            $data = $request->validate([
                'focus'          => 'nullable|string|max:200',
                'version'        => 'nullable|string|max:20',
                'period_start'   => 'required|date',
                'period_end'     => 'required|date|after:period_start',
                'responsible_id' => 'nullable|exists:users,id',
                'status'         => 'required|in:' . implode(',', array_keys(MacroPlan::$statuses)),
                'disciplines'    => 'nullable|array',
                'disciplines.*'  => 'string',
                'verba_mensal'   => 'nullable|string|max:30',
            ]);
            // Versão e disciplinas saíram do formulário (planejamento enxuto, 2026-10): só
            // grava se vierem — senão o salvar apagaria o que já existe.
            foreach (['version', 'disciplines'] as $legacy) {
                if (!$request->has($legacy)) {
                    unset($data[$legacy]);
                }
            }

            // Verba mora em bloco1.verba_total (onde o Orçamento sempre leu); o resto do
            // bloco antigo fica como está.
            $verba = $data['verba_mensal'] ?? null;
            unset($data['verba_mensal']);
            if ($request->has('verba_mensal')) {
                $data['bloco1'] = array_merge($macroplan->bloco1 ?? [], ['verba_total' => $verba]);
            }

            $macroplan->update($data);
            if ($request->has('verba_mensal')) {
                $this->syncAdBudget($macroplan, $verba);
            }

            return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => 'meta'])
                ->with('success', 'Planejamento atualizado.');
        }

        // Atualizar bloco de conteúdo
        $allowed = ['bloco1', 'bloco2', 'bloco4', 'bloco5'];
        if (!in_array($block, $allowed)) {
            abort(422);
        }

        $blockData = $request->except(['_token', '_method', '_block']);
        $macroplan->update([$block => $blockData]);

        if ($block === 'bloco1') {
            $this->syncAdBudget($macroplan, $blockData['verba_total'] ?? null);
        }

        return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => $block])
            ->with('success', 'Bloco salvo com sucesso.');
    }

    /**
     * Se o valor de verba informado no planejamento diverge do orçamento
     * atual do cliente, lança uma nova entrada no histórico de orçamento.
     */
    private function syncAdBudget(MacroPlan $macroplan, ?string $verbaTotalRaw): void
    {
        \App\Services\MacroPlanBudgetSync::sync($macroplan, $this->parseMoneyValue($verbaTotalRaw), 'ao salvar o planejamento');
    }

    private function parseMoneyValue(?string $raw): ?float
    {
        return \App\Services\MacroPlanBudgetSync::parseMoney($raw);
    }

    public function destroy(MacroPlan $macroplan)
    {
        $clientId = $macroplan->client_id;
        $macroplan->delete();

        return redirect()->route('clients.show', [$clientId, 'tab' => 'planejamentos'])
            ->with('success', 'Macroplanejamento removido.');
    }

    // Edição rápida de status direto na listagem/detalhe, sem precisar do
    // form completo de "Configurações" (mesmo padrão de MeetingController::updateStatus()).
    // Reunião de discussão das campanhas/projetos — pauta + equipe de cada item, um
    // agendamento só (ver ProjectDiscussionMeetingService).
    public function scheduleDiscussion(Request $request, MacroPlan $macroplan, ProjectDiscussionMeetingService $service)
    {
        $data = $request->validate([
            'scheduled_at'     => 'required|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'modality'         => 'required|in:' . implode(',', array_keys(Meeting::$modalities)),
            'online_link'      => 'nullable|url|max:500',
            'location'         => 'nullable|string|max:255',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|uuid',
            'items.*.team'     => 'nullable|array',
            'items.*.team.*'   => 'integer|exists:users,id',
        ], [
            'items.required' => 'Marque ao menos uma campanha ou projeto pra pauta.',
        ]);

        try {
            $meeting = $service->schedule($macroplan, $data, array_values($data['items']), Auth::id());
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Reunião de discussão agendada — participantes avisados.');
    }

    public function updateStatus(Request $request, MacroPlan $macroplan)
    {
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(MacroPlan::$statuses)),
        ]);

        $macroplan->update($data);

        return redirect()->back()->with('success', 'Status atualizado.');
    }
}

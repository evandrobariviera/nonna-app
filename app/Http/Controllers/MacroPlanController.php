<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\User;
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

        return view('macroplans.create', compact('client', 'clients', 'users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'      => 'required|uuid|exists:clients,id',
            'title'          => 'required|string|max:200',
            'period_start'   => 'required|date',
            'period_end'     => 'required|date|after:period_start',
            'responsible_id' => 'nullable|exists:users,id',
            'status'         => 'required|in:' . implode(',', array_keys(MacroPlan::$statuses)),
        ]);

        $plan = MacroPlan::create([
            ...$data,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('macroplans.edit', $plan)
            ->with('success', 'Macroplanejamento criado. Preencha os blocos abaixo.');
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
            'client', 'responsible', 'projects.tasks', 'attachments.uploadedBy', 'meetings', 'activities.user',
            'tasks' => fn ($q) => $q->whereNull('project_id')->orderByDesc('created_at'),
        ]);
        $users          = User::orderBy('name')->get(['id', 'name']);
        // Planejamento enxuto (2026-10): abre no Documento (HTML). Blocos antigos só entram
        // na navegação quando têm conteúdo, e em modo leitura.
        $legacyBlocks = $macroplan->legacyBlocks();
        $mainDocument = $macroplan->mainDocument();
        $currentBlock = $request->get('bloco', 'documento');

        if (!in_array($currentBlock, array_merge(['documento', 'bloco3', 'meta'], $legacyBlocks), true)) {
            $currentBlock = 'documento';
        }

        return view('macroplans.edit', compact('macroplan', 'users', 'currentBlock', 'legacyBlocks', 'mainDocument'));
    }

    public function update(Request $request, MacroPlan $macroplan)
    {
        $block = $request->input('_block');

        // Atualizar metadados gerais
        if ($block === 'meta') {
            $data = $request->validate([
                'title'          => 'required|string|max:200',
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
        $newValue = $this->parseMoneyValue($verbaTotalRaw);
        if ($newValue === null) {
            return;
        }

        $client = $macroplan->client ?? Client::find($macroplan->client_id);
        $current = $client->currentAdBudget();

        if ($current && abs((float) $current->monthly_budget - $newValue) < 0.01) {
            return;
        }

        $client->adBudgets()->create([
            'monthly_budget' => $newValue,
            'start_date'     => now()->toDateString(),
            'notes'          => "Atualizado ao salvar o Bloco 01 do planejamento \"{$macroplan->title}\".",
            'created_by'     => Auth::id(),
        ]);
    }

    private function parseMoneyValue(?string $raw): ?float
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.,]/', '', $raw);
        if ($clean === '') {
            return null;
        }

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
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
    public function updateStatus(Request $request, MacroPlan $macroplan)
    {
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(MacroPlan::$statuses)),
        ]);

        $macroplan->update($data);

        return redirect()->back()->with('success', 'Status atualizado.');
    }
}

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
        $query = MacroPlan::with(['client', 'responsible'])
            ->orderByDesc('period_start');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        $macroplans = $query->paginate(20)->withQueryString();
        $clients    = Client::orderBy('company_name')->get(['id', 'company_name']);

        return view('macroplans.index', compact('macroplans', 'clients'));
    }

    public function create(Request $request)
    {
        $client  = $request->filled('client_id')
            ? Client::findOrFail($request->client_id)
            : null;
        $clients = Client::orderBy('company_name')->get(['id', 'company_name']);
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
            'status'         => 'required|in:draft,active,closed',
        ]);

        $plan = MacroPlan::create([
            ...$data,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('macroplans.edit', $plan)
            ->with('success', 'Macroplanejamento criado. Preencha os blocos abaixo.');
    }

    public function edit(MacroPlan $macroplan, Request $request)
    {
        $macroplan->load(['client', 'responsible', 'projects.tasks']);
        $users          = User::orderBy('name')->get(['id', 'name']);
        $currentBlock   = $request->get('bloco', 'bloco1');

        if (!array_key_exists($currentBlock, MacroPlan::$blocks)) {
            $currentBlock = 'bloco1';
        }

        return view('macroplans.edit', compact('macroplan', 'users', 'currentBlock'));
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
                'status'         => 'required|in:draft,active,closed',
                'disciplines'    => 'nullable|array',
                'disciplines.*'  => 'string',
            ]);
            $data['disciplines'] = $data['disciplines'] ?? [];
            $macroplan->update($data);

            return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => 'bloco1'])
                ->with('success', 'Metadados atualizados.');
        }

        // Atualizar bloco de conteúdo
        $allowed = ['bloco1', 'bloco2', 'bloco4', 'bloco5'];
        if (!in_array($block, $allowed)) {
            abort(422);
        }

        $macroplan->update([$block => $request->except(['_token', '_method', '_block'])]);

        return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => $block])
            ->with('success', 'Bloco salvo com sucesso.');
    }

    public function destroy(MacroPlan $macroplan)
    {
        $clientId = $macroplan->client_id;
        $macroplan->delete();

        return redirect()->route('clients.show', [$clientId, 'tab' => 'planejamentos'])
            ->with('success', 'Macroplanejamento removido.');
    }
}

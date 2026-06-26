<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\AiProvider;
use App\Models\AiApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiAgentController extends Controller
{
    public function index()
    {
        $agents = AiAgent::with(['provider', 'createdBy'])
            ->orderByDesc('created_at')
            ->get();

        return view('ai.agents.index', compact('agents'));
    }

    public function create()
    {
        $providers = AiProvider::where('is_active', true)->with('apiKeys')->orderBy('name')->get();
        return view('ai.agents.create', compact('providers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:150',
            'description'   => 'nullable|string|max:500',
            'system_prompt' => 'required|string',
            'provider_id'   => 'required|uuid|exists:ai_providers,id',
            'api_key_id'    => 'nullable|uuid|exists:ai_api_keys,id',
            'model'         => 'required|string|max:100',
            'temperature'   => 'required|numeric|min:0|max:2',
            'max_tokens'    => 'required|integer|min:128|max:128000',
            'context_scope' => 'required|in:global,client',
        ]);

        AiAgent::create(array_merge($data, ['created_by' => Auth::id()]));

        return redirect()->route('ai.agents.index')->with('success', 'Agente criado com sucesso.');
    }

    public function edit(AiAgent $agent)
    {
        $providers = AiProvider::where('is_active', true)->with('apiKeys')->orderBy('name')->get();
        return view('ai.agents.edit', compact('agent', 'providers'));
    }

    public function update(Request $request, AiAgent $agent)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:150',
            'description'   => 'nullable|string|max:500',
            'system_prompt' => 'required|string',
            'provider_id'   => 'required|uuid|exists:ai_providers,id',
            'api_key_id'    => 'nullable|uuid|exists:ai_api_keys,id',
            'model'         => 'required|string|max:100',
            'temperature'   => 'required|numeric|min:0|max:2',
            'max_tokens'    => 'required|integer|min:128|max:128000',
            'context_scope' => 'required|in:global,client',
            'is_active'     => 'boolean',
        ]);

        $agent->update($data);

        return redirect()->route('ai.agents.index')->with('success', 'Agente atualizado.');
    }

    public function destroy(AiAgent $agent)
    {
        $agent->delete();
        return redirect()->route('ai.agents.index')->with('success', 'Agente removido.');
    }

    public function toggleActive(AiAgent $agent)
    {
        $agent->update(['is_active' => !$agent->is_active]);
        return back();
    }
}

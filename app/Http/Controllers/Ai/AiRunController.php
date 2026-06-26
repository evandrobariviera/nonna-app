<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Client\RequestException;

class AiRunController extends Controller
{
    public function __construct(private AiService $aiService) {}

    /**
     * Endpoint AJAX chamável de qualquer ponto do sistema.
     *
     * POST /ia/run
     * {
     *   agent_id:  uuid do agente
     *   message:   texto enviado ao agente (pergunta, conteúdo a processar, etc.)
     *   context:   { chave: valor } para substituir {chave} no system_prompt (opcional)
     *   client_id: uuid do cliente para rastreio (opcional)
     *   trigger:   identificador do ponto de acionamento, ex: 'caption_writer' (opcional)
     * }
     */
    public function run(Request $request)
    {
        $request->validate([
            'agent_id'  => 'required|uuid|exists:pgsql.ai_agents,id',
            'message'   => 'required|string|max:100000',
            'context'   => 'nullable|array',
            'client_id' => 'nullable|uuid',
            'trigger'   => 'nullable|string|max:100',
        ]);

        $agent = AiAgent::with('provider')->findOrFail($request->agent_id);

        if (!$agent->is_active) {
            return response()->json(['error' => 'Agente inativo.'], 422);
        }

        try {
            $result = $this->aiService->run(
                agent:       $agent,
                userMessage: $request->input('message'),
                context:     $request->input('context', []),
                userId:      Auth::id(),
                clientId:    $request->input('client_id'),
                trigger:     $request->input('trigger', 'api'),
            );

            return response()->json(['response' => $result]);

        } catch (RequestException $e) {
            report($e);
            return response()->json([
                'error' => 'Erro na API do provider: ' . $e->response?->json('error.message', $e->getMessage()),
            ], 502);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Página de teste interativo de um agente (painel interno).
     */
    public function show(AiAgent $agent)
    {
        $agent->loadMissing('provider', 'apiKey');
        return view('ai.agents.show', compact('agent'));
    }
}

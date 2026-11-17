<?php

namespace App\Http\Controllers;

use App\Models\AgentConversation;
use App\Models\AgentMessage;
use App\Services\Agent\AgentActionExecutor;
use App\Services\Agent\AgentRunner;
use App\Services\Agent\AgentTranscript;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Página do Agente Nonna (/agente) — conversa em tela inteira, estilo ChatGPT.
 * Cada conversa é só de quem a criou. Acesso controlado pelo Gate "use-agent".
 */
class AgentController extends Controller
{
    public function index(?AgentConversation $conversation = null)
    {
        if ($conversation?->exists) {
            $this->authorizeConversation($conversation);
        }

        $conversations = AgentConversation::where('user_id', auth()->id())
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get(['id', 'title', 'last_message_at']);

        return view('agent.index', [
            'conversations' => $conversations->map(fn ($c) => [
                'id'    => $c->id,
                'title' => $c->title ?: 'Nova conversa',
                'when'  => $c->last_message_at?->diffForHumans(short: true),
            ])->all(),
            'current'  => $conversation?->exists ? $conversation->id : null,
            'messages' => $conversation?->exists ? AgentTranscript::toUi($conversation->messages()->get()) : [],
        ]);
    }

    public function send(Request $request, AgentRunner $runner)
    {
        $data = $request->validate([
            'message'         => 'required|string|max:20000',
            'conversation_id' => 'nullable|uuid',
        ]);

        $conversation = !empty($data['conversation_id'])
            ? AgentConversation::findOrFail($data['conversation_id'])
            : AgentConversation::create(['user_id' => auth()->id()]);
        $this->authorizeConversation($conversation);

        // Algumas perguntas fazem o agente consultar várias coisas em sequência.
        set_time_limit(300);

        try {
            $created = $runner->reply($conversation, $request->user(), $data['message']);
        } catch (\Throwable $e) {
            Log::warning('Agente Nonna falhou', ['conversation' => $conversation->id, 'error' => $e->getMessage()]);
            return response()->json([
                'conversation_id' => $conversation->id,
                'error'           => $e instanceof \RuntimeException ? $e->getMessage() : 'O agente não conseguiu responder agora. Tente de novo em instantes.',
            ], 422);
        }

        return response()->json([
            'conversation_id' => $conversation->id,
            'title'           => $conversation->title,
            'items'           => AgentTranscript::toUi($created->reject(fn ($m) => $m->role === 'user')),
        ]);
    }

    public function confirm(Request $request, AgentMessage $message)
    {
        $this->authorizeProposal($message);

        // Trava contra clique duplo: só um pedido consegue tirar de "pending".
        $locked = AgentMessage::whereKey($message->id)->where('proposal_status', 'pending')
            ->update(['proposal_status' => 'processing']);
        abort_unless($locked === 1, 422, 'Essa proposta já está sendo criada.');

        try {
            $result = (new AgentActionExecutor($request->user()))->confirm($message);
        } catch (\Throwable $e) {
            report($e);
            $message->update(['proposal_status' => 'pending']);
            return response()->json(['error' => $e instanceof \RuntimeException ? $e->getMessage() : 'Não deu pra criar agora. Tente de novo.'], 422);
        }

        $message->update(['proposal_status' => 'confirmed', 'proposal_result' => $result]);

        return response()->json(['item' => AgentTranscript::item($message)]);
    }

    public function discard(AgentMessage $message)
    {
        $this->authorizeProposal($message);
        $message->update(['proposal_status' => 'discarded']);

        return response()->json(['item' => AgentTranscript::item($message)]);
    }

    public function destroy(AgentConversation $conversation)
    {
        $this->authorizeConversation($conversation);
        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeConversation(AgentConversation $conversation): void
    {
        abort_unless($conversation->user_id === auth()->id(), 404);
    }

    private function authorizeProposal(AgentMessage $message): void
    {
        $this->authorizeConversation($message->conversation);
        abort_unless($message->proposal && $message->proposal_status === 'pending', 422, 'Essa proposta já foi resolvida.');
    }
}

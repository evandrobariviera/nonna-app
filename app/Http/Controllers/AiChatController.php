<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\AiChat;
use App\Models\Task;
use App\Services\AiService;
use App\Services\ContextResolver;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function storeForTask(Request $request, Task $task)
    {
        $request->validate([
            'agent_id' => 'required|uuid|exists:pgsql.ai_agents,id',
            'message'  => 'required|string|max:5000',
        ]);

        $agent = AiAgent::with('provider')->findOrFail($request->agent_id);

        $chat = AiChat::firstOrCreate([
            'entity_type' => 'task',
            'entity_id'   => $task->id,
        ]);

        $chat->messages()->create([
            'role'     => 'user',
            'content'  => $request->message,
            'user_id'  => auth()->id(),
            'agent_id' => $agent->id,
        ]);

        $history = $chat->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        try {
            $context = ContextResolver::forTask($task);
            $output  = app(AiService::class)->chat(
                agent:    $agent,
                history:  $history,
                context:  $context,
                userId:   auth()->id(),
                clientId: $task->client_id,
            );

            $msg = $chat->messages()->create([
                'role'     => 'assistant',
                'content'  => $output,
                'agent_id' => $agent->id,
            ]);

            return response()->json([
                'id'         => $msg->id,
                'role'       => 'assistant',
                'content'    => $output,
                'agent_name' => $agent->name,
                'user_name'  => null,
                'time'       => $msg->created_at->format('H:i'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}

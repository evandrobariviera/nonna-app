<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\AiChat;
use App\Models\FunctionalRole;
use App\Models\Project;
use App\Models\Task;
use App\Services\AiService;
use App\Services\ContextResolver;
use App\Services\TaskDraftService;
use Illuminate\Http\Request;

// Chat conversacional do Assistente de Lançamento de Tarefas, escopado a um
// Projeto (ver ContextResolver::forProject()) — irmão de AiChatController,
// mas com saída sempre em JSON estruturado (draft_tasks) em vez de texto
// livre, porque o objetivo aqui é gerar tarefas revisáveis, não só conversar.
class ProjectTaskAssistantController extends Controller
{
    public function chat(Request $request, Project $project)
    {
        $request->validate([
            'agent_id' => 'required|uuid|exists:pgsql.ai_agents,id',
            'message'  => 'required|string|max:5000',
        ]);

        $agent = AiAgent::with('provider')->findOrFail($request->agent_id);

        $chat = AiChat::firstOrCreate([
            'entity_type' => 'project',
            'entity_id'   => $project->id,
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
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        try {
            $context = ContextResolver::forProject($project);
            $output  = app(AiService::class)->chatStructured(
                agent:    $agent,
                history:  $history,
                context:  $context,
                userId:   auth()->id(),
                clientId: $project->client_id,
            );

            $message = is_string($output['message'] ?? null) ? $output['message'] : '';
            [$drafts, $warnings] = $this->sanitizeDrafts($output['draft_tasks'] ?? []);

            // draft_tasks NÃO é persistido no histórico (AiChatMessage.content é texto
            // livre) — só o texto humano da resposta. Ao recarregar a página os cards
            // da última resposta somem; decisão aceita pra não inflar o schema agora.
            $msg = $chat->messages()->create([
                'role'     => 'assistant',
                'content'  => $message,
                'agent_id' => $agent->id,
            ]);

            return response()->json([
                'id'          => $msg->id,
                'role'        => 'assistant',
                'content'     => $message,
                'agent_name'  => $agent->name,
                'user_name'   => null,
                'time'        => $msg->created_at->format('H:i'),
                'draft_tasks' => $drafts,
                'warnings'    => $warnings,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // Nunca confia no que veio do client mesmo já validado/sanitizado em
    // chat() — revalida do zero antes de criar tarefas de verdade.
    public function confirmDrafts(Request $request, Project $project, TaskDraftService $service)
    {
        $data = $request->validate([
            'tasks'                      => 'required|array|min:1',
            'tasks.*.title'              => 'required|string|max:300',
            'tasks.*.description'        => 'nullable|string',
            'tasks.*.task_type'          => 'required|in:' . implode(',', array_keys(Task::$types)),
            'tasks.*.destination'        => 'nullable|in:' . implode(',', array_keys(Task::$destinations)),
            'tasks.*.priority'           => 'nullable|in:' . implode(',', array_keys(Task::$priorities)),
            'tasks.*.due_offset_days'    => 'nullable|integer|min:0|max:3650',
            'tasks.*.functional_role_id' => 'nullable|uuid|exists:pgsql.functional_roles,id',
        ]);

        $result = $service->createFromDrafts($project, $data['tasks'], auth()->id(), 'ai_assistant');

        // Encerra a conversa automaticamente após confirmar — o assunto foi
        // resolvido (as tarefas já existem), então a próxima vez que o painel
        // abrir vem em branco, pronto pra um novo pedido (pedido do Evandro,
        // pra não ficar vendo conversa antiga sem querer).
        $this->clearChatFor($project);

        return response()->json([
            'created'  => $result['tasks']->count(),
            'warnings' => $result['warnings'],
        ]);
    }

    // Botão "Nova conversa" no painel — apaga o histórico de chat deste
    // projeto. É rascunho de trabalho, não registro de auditoria (diferente
    // de task_activities), então apagar de vez é aceitável aqui.
    public function clearChat(Project $project)
    {
        $this->clearChatFor($project);

        return response()->json(['cleared' => true]);
    }

    private function clearChatFor(Project $project): void
    {
        AiChat::where('entity_type', 'project')->where('entity_id', $project->id)->delete();
    }

    /**
     * Valida cada item de draft_tasks vindo da IA contra os enums reais de
     * Task. Só descarta o item quando falta até o título — sem ele não dá
     * nem pra mostrar um cartão. task_type/destination/priority inválidos ou
     * ausentes viram null em vez de derrubar o item inteiro: a tarefa real
     * exige task_type (ver Task::storeRules()), mas isso é cobrado na hora
     * de CONFIRMAR (confirmDrafts() revalida), não na hora de gerar o
     * rascunho — o cartão aparece com o campo em branco pro usuário escolher
     * (ver resources/views/projects/_task-assistant-drawer.blade.php).
     * Também resolve functional_role_key (como a IA referencia um
     * responsável, ver ContextResolver::functionalRolesCatalog()) pro
     * functional_role_id que o front-end precisa pra pré-selecionar o campo.
     *
     * @return array{0: array, 1: string[]}
     */
    private function sanitizeDrafts(array $rawDrafts): array
    {
        $valid    = [];
        $warnings = [];

        foreach ($rawDrafts as $i => $item) {
            if (!is_array($item) || empty($item['title'])) {
                $warnings[] = 'Um item de rascunho sem título foi descartado (posição ' . ($i + 1) . ').';
                continue;
            }

            $taskType = $item['task_type'] ?? null;
            if (!$taskType || !array_key_exists($taskType, Task::$types)) {
                $taskType = null;
            }

            $destination = $item['destination'] ?? null;
            if ($destination && !array_key_exists($destination, Task::$destinations)) {
                $destination = null;
            }

            $priority = $item['priority'] ?? null;
            if ($priority && !array_key_exists($priority, Task::$priorities)) {
                $priority = null;
            }

            $dueOffsetDays = is_numeric($item['due_offset_days'] ?? null) ? (int) $item['due_offset_days'] : null;

            $role = !empty($item['functional_role_key'])
                ? FunctionalRole::where('key', $item['functional_role_key'])->first()
                : null;

            $valid[] = [
                'title'                => (string) $item['title'],
                'description'          => $item['description'] ?? null,
                'task_type'            => $taskType,
                'destination'          => $destination,
                'priority'             => $priority,
                'due_offset_days'      => $dueOffsetDays,
                'functional_role_id'   => $role?->id,
                'functional_role_name' => $role?->name,
            ];
        }

        return [$valid, $warnings];
    }
}

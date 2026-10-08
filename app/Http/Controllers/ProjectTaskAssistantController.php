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
use Carbon\Carbon;
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
            'message' => 'required|string|max:5000',
        ]);

        // Agente fixo (não dá pra escolher outro na tela) — os demais agentes não
        // respondem no formato de rascunho de tarefas.
        $agent = AiAgent::bySlug(AiAgent::SLUG_TASK_ASSISTANT);
        if (!$agent) {
            return response()->json(['error' => 'O agente "Assistente de Lançamento de Tarefas" não está configurado ou está inativo.'], 422);
        }

        // Uma conversa por pessoa por projeto — ninguém vê nem apaga a do outro.
        $chat = AiChat::firstOrCreate([
            'entity_type' => 'project',
            'entity_id'   => $project->id,
            'user_id'     => auth()->id(),
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
            [$drafts, $warnings] = $this->sanitizeDrafts($project, $output['draft_tasks'] ?? []);

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
            'tasks.*.due_date'           => 'nullable|date_format:Y-m-d',
            'tasks.*.functional_role_id' => 'nullable|uuid|exists:pgsql.functional_roles,id',
        ]);

        $result = $service->createFromDrafts($project, $data['tasks'], auth()->id(), 'ai_assistant');

        // Encerra a conversa automaticamente após confirmar — o assunto foi
        // resolvido (as tarefas já existem), então a próxima vez que o painel
        // abrir vem em branco, pronto pra um novo pedido (pedido do Evandro,
        // pra não ficar vendo conversa antiga sem querer).
        $this->clearChatFor($project, auth()->id());

        return response()->json([
            'created'  => $result['tasks']->count(),
            'warnings' => $result['warnings'],
        ]);
    }

    // Botão "Nova conversa" no painel — apaga o histórico de chat de quem
    // clicou, neste projeto (a conversa dos outros fica intacta). É rascunho
    // de trabalho, não registro de auditoria (diferente de task_activities),
    // então apagar de vez é aceitável aqui.
    public function clearChat(Project $project)
    {
        $this->clearChatFor($project, auth()->id());

        return response()->json(['cleared' => true]);
    }

    private function clearChatFor(Project $project, int $userId): void
    {
        AiChat::where('entity_type', 'project')
            ->where('entity_id', $project->id)
            ->where('user_id', $userId)
            ->delete();
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
     * Prazo sai sempre como data (due_date AAAA-MM-DD) — se a IA ainda mandar
     * due_offset_days (formato antigo do prompt), converte a partir do início
     * do projeto, mesma regra de TaskDraftService::resolveDueDate().
     *
     * @return array{0: array, 1: string[]}
     */
    private function sanitizeDrafts(Project $project, array $rawDrafts): array
    {
        $valid    = [];
        $warnings = [];
        $roles    = FunctionalRole::get(['id', 'key', 'name'])->keyBy('key');

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

            $dueDate = null;
            if (is_string($item['due_date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $item['due_date'])) {
                // Descarta data impossível (ex: 2026-02-30) em vez de deixar o
                // Carbon "rolar" pro mês seguinte.
                $parsed  = Carbon::createFromFormat('!Y-m-d', $item['due_date']);
                $dueDate = $parsed && $parsed->format('Y-m-d') === $item['due_date'] ? $item['due_date'] : null;
            } elseif (is_numeric($item['due_offset_days'] ?? null)) {
                $base    = $project->start_date ? Carbon::parse($project->start_date) : now();
                $dueDate = $base->copy()->addDays((int) $item['due_offset_days'])->format('Y-m-d');
            }

            $role = !empty($item['functional_role_key']) ? $roles->get($item['functional_role_key']) : null;

            $valid[] = [
                'title'                => (string) $item['title'],
                'description'          => is_string($item['description'] ?? null) ? $item['description'] : null,
                'task_type'            => $taskType,
                'destination'          => $destination,
                'priority'             => $priority,
                'due_date'             => $dueDate,
                'functional_role_id'   => $role?->id,
                'functional_role_name' => $role?->name,
            ];
        }

        return [$valid, $warnings];
    }
}

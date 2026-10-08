<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskDelivery;
use App\Services\TaskDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// Janela "Entrega" — única porta pra Revisão Interna feita por pessoa (ver TaskDeliveryService).
class TaskDeliveryController extends Controller
{
    public function store(Request $request, Task $task, TaskDeliveryService $service): JsonResponse
    {
        // Chamado só via fetch: validate() padrão redirecionaria (302) em vez de 422.
        $validator = Validator::make($request->all(), [
            'fully_done' => ['required', 'boolean'],
            'missing'    => ['nullable', 'required_if:fully_done,false,0', 'string', 'min:10', 'max:2000'],
            'body'       => ['required', 'string', 'min:40', 'max:5000'],
        ], [
            'missing.required_if' => 'Conte o que faltou e por quê.',
            'missing.min'         => 'Conte um pouco mais sobre o que faltou (mínimo 10 caracteres).',
            'body.required'       => 'Escreva o retorno da entrega.',
            'body.min'            => 'O retorno está curto demais — conte o que fez e por que desse jeito (mínimo 40 caracteres).',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        if (in_array($task->status, ['concluido', 'cancelado'], true)) {
            return response()->json(['message' => 'Tarefa encerrada não pode ser entregue pra revisão.'], 422);
        }

        $service->deliver($task, $request->user(), $validator->validated());

        return response()->json(['success' => true]);
    }

    // Só o Responsável da tarefa corrige o texto de um retorno (ex: deixar
    // pronto pro cliente). Fica registrado no histórico da tarefa.
    public function update(Request $request, Task $task, TaskDelivery $delivery): RedirectResponse
    {
        abort_unless($delivery->task_id === $task->id, 404);
        abort_unless($task->isResponsible($request->user()), 403, 'Só o Responsável da tarefa pode editar o retorno de entrega.');

        $data = $request->validate([
            'body'    => ['required', 'string', 'min:40', 'max:5000'],
            'missing' => [$delivery->fully_done ? 'nullable' : 'required', 'string', 'min:10', 'max:2000'],
        ], [
            'body.min'    => 'O retorno está curto demais (mínimo 40 caracteres).',
            'missing.min' => 'Conte um pouco mais sobre o que faltou (mínimo 10 caracteres).',
        ]);

        $delivery->update([
            'body'    => $data['body'],
            'missing' => $delivery->fully_done ? $delivery->missing : $data['missing'],
        ]);

        TaskActivity::log($task, 'delivery_edited', null, $delivery->created_at->format('d/m/Y H:i'));

        return back()->with('success', 'Retorno de entrega atualizado.');
    }
}

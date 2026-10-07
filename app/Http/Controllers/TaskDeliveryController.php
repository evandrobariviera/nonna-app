<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\TaskDeliveryService;
use Illuminate\Http\JsonResponse;
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
}

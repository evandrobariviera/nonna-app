<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskDelivery;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Trava de entrega: toda ida pra Revisão Interna feita por uma PESSOA exige o retorno
 * de entrega (executou por completo? + texto pensado pro cliente). A única porta de
 * entrada é deliver() — as rotas comuns de status (barra da tarefa, quadros, listas,
 * formulários) chamam guard() e, em vez de mudar o status, devolvem "precisa do
 * retorno" pra tela abrir a janela de entrega (resources/js/task-delivery.js).
 *
 * Movimentos do próprio sistema (cancelar rodada de aprovação, automações, import do
 * ClickUp) não passam por aqui — ninguém está entregando nada nesses casos.
 */
class TaskDeliveryService
{
    public const STATUS = 'revisao_interna';

    public static function requiresDelivery(Task $task, ?string $newStatus): bool
    {
        return $newStatus === self::STATUS && $task->status !== self::STATUS;
    }

    // Barra a mudança e responde no formato que a tela entende: JSON 422 com
    // delivery_required (fetch) ou volta com flash que abre a janela sozinha (form).
    public static function guard(Request $request, Task $task, ?string $newStatus): void
    {
        if (!self::requiresDelivery($task, $newStatus)) {
            return;
        }

        $message = 'Pra mandar pra Revisão Interna, conte o que você fez no retorno de entrega.';
        $payload = self::payload($task);

        if ($request->wantsJson() || $request->ajax()) {
            throw new HttpResponseException(response()->json([
                'message'           => $message,
                'delivery_required' => $payload,
            ], 422));
        }

        throw new HttpResponseException(
            redirect()->back()->withInput()->with('warning', $message)->with('delivery_required', $payload)
        );
    }

    public static function payload(Task $task): array
    {
        return [
            'task_id' => $task->id,
            'title'   => $task->title,
            'url'     => route('tasks.deliver', $task),
        ];
    }

    /**
     * @param  array{fully_done: bool, missing?: ?string, body: string}  $data
     */
    public function deliver(Task $task, User $user, array $data): TaskDelivery
    {
        return DB::connection('pgsql')->transaction(function () use ($task, $user, $data) {
            $delivery = TaskDelivery::create([
                'task_id'     => $task->id,
                'user_id'     => $user->id,
                'fully_done'  => (bool) $data['fully_done'],
                'missing'     => $data['fully_done'] ? null : trim((string) ($data['missing'] ?? '')),
                'body'        => trim($data['body']),
                'from_status' => $task->status,
            ]);

            // update() normal (não quiet): TaskObserver registra a transição de status,
            // histórico e automações de "status mudou" — igual a qualquer mudança de status.
            if ($task->status !== self::STATUS) {
                $task->update(['status' => self::STATUS]);
            }

            return $delivery;
        });
    }
}

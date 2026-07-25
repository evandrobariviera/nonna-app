<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\TaskApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TaskApprovalController extends Controller
{
    public function __construct(private TaskApprovalService $service) {}

    public function store(Request $request, Task $task)
    {
        $request->validate([
            'attachment_ids'   => 'required|array|min:1',
            'attachment_ids.*' => [
                'required', 'uuid',
                Rule::exists('task_attachments', 'id')
                    ->where('task_id', $task->id)
                    ->where('kind', 'entregavel'),
            ],
            'notes'            => 'nullable|string|max:2000',
        ]);

        if ($task->approvalRounds()->where('status', 'pending')->exists()) {
            return redirect()->route('tasks.show', $task)
                ->with('warning', 'Essa tarefa já tem uma rodada de aprovação pendente. Aguarde a resposta do cliente antes de enviar de novo.');
        }

        $this->service->submitForApproval(
            $task,
            Auth::user(),
            $request->attachment_ids,
            $request->notes,
        );

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Rodada de aprovação criada. Envie pro cliente na Central de Aprovações.');
    }
}

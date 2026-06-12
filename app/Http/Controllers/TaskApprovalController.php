<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\TaskApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskApprovalController extends Controller
{
    public function __construct(private TaskApprovalService $service) {}

    public function store(Request $request, Task $task)
    {
        $request->validate([
            'attachment_ids'   => 'required|array|min:1',
            'attachment_ids.*' => 'required|uuid|exists:task_attachments,id',
            'notes'            => 'nullable|string|max:2000',
        ]);

        $this->service->submitForApproval(
            $task,
            Auth::user(),
            $request->attachment_ids,
            $request->notes,
        );

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Tarefa enviada para aprovação. Os contatos serão notificados.');
    }
}

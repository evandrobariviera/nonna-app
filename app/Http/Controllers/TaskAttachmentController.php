<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\TaskApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TaskAttachmentController extends Controller
{
    public function store(Request $request, Task $task, TaskApprovalService $approvalService)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100 MB
            'kind' => 'required|in:insumo,entregavel',
        ]);

        $file = $request->file('file');
        $disk = config('filesystems.default', 'r2');
        $path = $file->store("tasks/{$task->id}", $disk);

        TaskAttachment::create([
            'task_id'     => $task->id,
            'filename'    => $file->getClientOriginalName(),
            'disk_path'   => $path,
            'disk'        => $disk,
            'mime_type'   => $file->getMimeType(),
            'size'        => $file->getSize(),
            'uploaded_by' => Auth::id(),
            'kind'        => $request->kind,
        ]);

        // A tarefa pode já estar em Aprovação + situação "Enviar para o cliente"
        // esperando só o anexo — reavalia agora que o arquivo chegou, porque o
        // upload não passa pelo TaskObserver (não muda status/situação da tarefa).
        $roundCreated = $approvalService->maybeAutoSubmitOnApprovalTransition($task);

        $redirect = redirect()->route('tasks.show', $task);

        return $roundCreated ? $redirect : $redirect->with('success', 'Arquivo anexado.');
    }

    public function destroy(Task $task, TaskAttachment $attachment)
    {
        abort_unless($attachment->task_id === $task->id, 403);

        Storage::disk($attachment->disk)->delete($attachment->disk_path);
        $attachment->delete();

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Anexo removido.');
    }
}

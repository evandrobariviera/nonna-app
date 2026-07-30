<?php

namespace App\Http\Controllers;

use App\Models\DeliverableFeedback;
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
            'files'   => 'required|array|min:1', // carrossel/múltiplas peças de uma vez
            'files.*' => 'file|max:102400', // 100 MB
            'kind'    => 'required|in:insumo,entregavel',
        ]);

        $disk = config('filesystems.default', 'r2');

        foreach ($request->file('files') as $file) {
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
        }

        // A tarefa pode já estar em Aprovação + situação "Enviar para o cliente"
        // esperando só o anexo — reavalia agora que o(s) arquivo(s) chegaram, porque
        // o upload não passa pelo TaskObserver (não muda status/situação da tarefa).
        $roundCreated = $approvalService->maybeAutoSubmitOnApprovalTransition($task);

        $redirect = redirect()->route('tasks.show', $task);
        $label = $request->file('files') && count($request->file('files')) > 1 ? 'Arquivos anexados.' : 'Arquivo anexado.';

        return $roundCreated ? $redirect : $redirect->with('success', $label);
    }

    public function destroy(Task $task, TaskAttachment $attachment)
    {
        abort_unless($attachment->task_id === $task->id, 403);

        // Excluir um entregável que já tem retorno do cliente apagaria o
        // feedback junto (FK com cascadeOnDelete) — perderia o histórico da
        // rodada. Se precisa de uma versão nova, é só anexar o arquivo
        // corrigido; o antigo fica registrado na rodada em que foi enviado.
        if (DeliverableFeedback::where('attachment_id', $attachment->id)->exists()) {
            return back()->with('warning', 'Esse arquivo já tem retorno do cliente registrado e não pode ser removido — anexe o material corrigido como um novo arquivo.');
        }

        Storage::disk($attachment->disk)->delete($attachment->disk_path);
        $attachment->delete();

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Anexo removido.');
    }
}

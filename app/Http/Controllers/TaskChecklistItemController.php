<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Support\RichTextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// "Item de ação": comentário virado checklist atribuído a alguém — mesmo fluxo do
// ClickUp (ícone na ação do comentário → escolhe a pessoa → cria o item). Título vem
// direto do texto do comentário (sem passo de digitar nada), de propósito — ver
// avaliação de UX confirmada com o Evandro antes de implementar.
class TaskChecklistItemController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $data = $request->validate([
            'comment_id'  => 'nullable|uuid|exists:pgsql.task_comments,id',
            'assigned_to' => 'required|exists:pgsql.users,id',
        ]);

        $title = null;
        if (!empty($data['comment_id'])) {
            $comment = TaskComment::findOrFail($data['comment_id']);
            abort_unless($comment->task_id === $task->id, 403);
            $title = RichTextSanitizer::toPlainText($comment->body);
        }

        if ($title === null || $title === '') {
            return back()->with('warning', 'Não foi possível criar o item — comentário sem texto.');
        }

        $task->checklistItems()->create([
            'source_comment_id' => $data['comment_id'] ?? null,
            'title'             => $title,
            'assigned_to'       => $data['assigned_to'],
            'created_by'        => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Item de ação criado.');
    }

    public function toggle(Task $task, TaskChecklistItem $item)
    {
        abort_unless($item->task_id === $task->id, 403);

        $item->update([
            'done'    => !$item->done,
            'done_at' => $item->done ? null : now(),
        ]);

        return redirect()->back();
    }

    public function destroy(Task $task, TaskChecklistItem $item)
    {
        abort_unless($item->task_id === $task->id, 403);

        $item->delete();

        return redirect()->back()->with('success', 'Item de ação removido.');
    }
}

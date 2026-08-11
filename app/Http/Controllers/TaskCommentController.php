<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use App\Support\RichTextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        // max bem maior que antes (era 5000) — comentário agora pode carregar HTML com
        // imagem colada em base64, que facilmente passa de 100-500 mil caracteres.
        $data = $request->validate([
            'body' => 'required|string|max:5000000',
        ]);

        $body = RichTextSanitizer::clean($data['body']);

        // Editor rico vazio manda "<p></p>" (string não-vazia) — required sozinho não
        // pega isso. Só bloqueia se não sobrou nem texto nem imagem depois de limpo.
        if (trim(strip_tags($body)) === '' && !str_contains($body, '<img')) {
            return back()->withErrors(['body' => 'O comentário não pode ficar vazio.'])->withFragment('comentarios');
        }

        $task->comments()->create([
            'user_id'           => Auth::id(),
            'body'              => $body,
            'visible_to_client' => $request->boolean('visible_to_client'),
        ]);

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Comentário adicionado.')
            ->withFragment('comentarios');
    }

    public function update(Request $request, Task $task, TaskComment $comment)
    {
        abort_unless($comment->task_id === $task->id, 403);
        abort_unless($comment->user_id === Auth::id(), 403);

        $data = $request->validate([
            'body' => 'required|string|max:5000000',
        ]);

        $body = RichTextSanitizer::clean($data['body']);

        if (trim(strip_tags($body)) === '' && !str_contains($body, '<img')) {
            return back()->withErrors(['body' => 'O comentário não pode ficar vazio.'])->withFragment('comentarios');
        }

        $comment->update(['body' => $body]);

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Comentário atualizado.')
            ->withFragment('comentarios');
    }

    public function destroy(Task $task, TaskComment $comment)
    {
        abort_unless($comment->task_id === $task->id, 403);
        abort_unless($comment->user_id === Auth::id(), 403);

        $comment->delete();

        return redirect()->route('tasks.show', $task)
            ->withFragment('comentarios');
    }
}

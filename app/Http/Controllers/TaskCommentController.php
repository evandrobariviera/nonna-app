<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $task->comments()->create([
            'user_id'           => Auth::id(),
            'body'              => $data['body'],
            'visible_to_client' => $request->boolean('visible_to_client'),
        ]);

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Comentário adicionado.')
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

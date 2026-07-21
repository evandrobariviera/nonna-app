<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $client = auth()->user()->client;

        abort_if($task->client_id !== $client->id, 403);

        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $task->comments()->create([
            'user_id'           => auth()->id(),
            'body'              => $data['body'],
            'visible_to_client' => true,
        ]);

        return back()->with('success', 'Comentário adicionado.')->withFragment('comentarios');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TaskAttachmentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100 MB
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
        ]);

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Arquivo anexado.');
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

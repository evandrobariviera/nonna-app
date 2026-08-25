<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Imagem colada/arrastada direto no editor rico (descrição da tarefa, comentário) —
// grava no storage e devolve uma URL permanente da própria app, nunca o link assinado
// do R2 direto (esse expira em 24h e quebraria a imagem depois de salva no HTML). Achado
// real: um comentário com imagem colada como base64 passou de 900 mil caracteres e
// deixava a tela pesada só naquela tarefa. Diferente de TaskAttachment — não é anexo
// listado, é conteúdo embutido no texto.
class TaskEditorImageController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $request->validate([
            'image' => 'required|image|max:15360', // 15 MB
        ]);

        $file     = $request->file('image');
        $filename = Str::uuid() . '.' . $file->extension();
        $disk     = config('filesystems.default', 'r2');

        $file->storeAs("tasks/{$task->id}/editor", $filename, $disk);

        return response()->json([
            'url' => route('tasks.editor-image.show', [$task, $filename]),
        ]);
    }

    public function show(Task $task, string $filename)
    {
        $disk = config('filesystems.default', 'r2');
        $path = "tasks/{$task->id}/editor/{$filename}";

        abort_unless(Storage::disk($disk)->exists($path), 404);

        if ($disk === 'r2') {
            return redirect(Storage::disk('r2')->temporaryUrl($path, now()->addHour()));
        }

        return response()->file(Storage::disk($disk)->path($path));
    }
}

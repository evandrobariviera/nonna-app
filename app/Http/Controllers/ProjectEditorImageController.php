<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Imagem colada/arrastada no editor rico dos comentários da campanha/projeto — mesmo
// padrão de TaskEditorImageController (ver ali o porquê de não usar base64 embutido).
class ProjectEditorImageController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $request->validate([
            'image' => 'required|image|max:15360', // 15 MB
        ]);

        $file     = $request->file('image');
        $filename = Str::uuid() . '.' . $file->extension();
        $disk     = config('filesystems.default', 'r2');

        $file->storeAs("projects/{$project->id}/editor", $filename, $disk);

        return response()->json([
            'url' => route('projects.editor-image.show', [$project, $filename]),
        ]);
    }

    public function show(Project $project, string $filename)
    {
        $disk = config('filesystems.default', 'r2');
        $path = "projects/{$project->id}/editor/{$filename}";

        abort_unless(Storage::disk($disk)->exists($path), 404);

        if ($disk === 'r2') {
            return redirect(Storage::disk('r2')->temporaryUrl($path, now()->addHour()));
        }

        return response()->file(Storage::disk($disk)->path($path));
    }
}

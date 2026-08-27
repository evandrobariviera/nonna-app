<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Imagem colada/arrastada no editor rico do artigo — mesmo padrão de
// TaskEditorImageController (ver ali o porquê de não usar base64 embutido).
class HelpArticleEditorImageController extends Controller
{
    public function store(Request $request, HelpArticle $article)
    {
        $request->validate([
            'image' => 'required|image|max:15360', // 15 MB
        ]);

        $file     = $request->file('image');
        $filename = Str::uuid() . '.' . $file->extension();
        $disk     = config('filesystems.default', 'r2');

        $file->storeAs("help-articles/{$article->id}/editor", $filename, $disk);

        return response()->json([
            'url' => route('help.editor-image.show', [$article, $filename]),
        ]);
    }

    public function show(HelpArticle $article, string $filename)
    {
        $disk = config('filesystems.default', 'r2');
        $path = "help-articles/{$article->id}/editor/{$filename}";

        abort_unless(Storage::disk($disk)->exists($path), 404);

        if ($disk === 'r2') {
            return redirect(Storage::disk('r2')->temporaryUrl($path, now()->addHour()));
        }

        return response()->file(Storage::disk($disk)->path($path));
    }
}

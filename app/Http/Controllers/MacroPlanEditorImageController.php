<?php

namespace App\Http\Controllers;

use App\Models\MacroPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Imagem colada/arrastada no editor rico da discussão do planejamento — mesmo padrão de
// ProjectEditorImageController.
class MacroPlanEditorImageController extends Controller
{
    public function store(Request $request, MacroPlan $macroplan)
    {
        $request->validate([
            'image' => 'required|image|max:15360', // 15 MB
        ]);

        $file     = $request->file('image');
        $filename = Str::uuid() . '.' . $file->extension();
        $disk     = config('filesystems.default', 'r2');

        $file->storeAs("macro-plans/{$macroplan->id}/editor", $filename, $disk);

        return response()->json([
            'url' => route('macroplans.editor-image.show', [$macroplan, $filename]),
        ]);
    }

    public function show(MacroPlan $macroplan, string $filename)
    {
        $disk = config('filesystems.default', 'r2');
        $path = "macro-plans/{$macroplan->id}/editor/{$filename}";

        abort_unless(Storage::disk($disk)->exists($path), 404);

        if ($disk === 'r2') {
            return redirect(Storage::disk('r2')->temporaryUrl($path, now()->addHour()));
        }

        return response()->file(Storage::disk($disk)->path($path));
    }
}

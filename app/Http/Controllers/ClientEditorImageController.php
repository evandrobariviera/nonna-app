<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Imagem colada/arrastada no editor rico do Briefing do cliente — mesmo padrão de
// TaskEditorImageController (ver ali o porquê de não usar base64 embutido).
class ClientEditorImageController extends Controller
{
    public function store(Request $request, Client $client)
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);

        $request->validate([
            'image' => 'required|image|max:15360', // 15 MB
        ]);

        $file     = $request->file('image');
        $filename = Str::uuid() . '.' . $file->extension();
        $disk     = config('filesystems.default', 'r2');

        $file->storeAs("clients/{$client->id}/editor", $filename, $disk);

        return response()->json([
            'url' => route('clients.editor-image.show', [$client, $filename]),
        ]);
    }

    public function show(Client $client, string $filename)
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);

        $disk = config('filesystems.default', 'r2');
        $path = "clients/{$client->id}/editor/{$filename}";

        abort_unless(Storage::disk($disk)->exists($path), 404);

        if ($disk === 'r2') {
            return redirect(Storage::disk('r2')->temporaryUrl($path, now()->addHour()));
        }

        return response()->file(Storage::disk($disk)->path($path));
    }
}

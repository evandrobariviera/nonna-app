<?php

namespace App\Http\Controllers;

use App\Models\MacroPlan;
use App\Models\MacroPlanAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MacroPlanAttachmentController extends Controller
{
    public function store(Request $request, MacroPlan $macroplan)
    {
        $request->validate([
            'file' => 'required|file|max:20480', // 20 MB
        ]);

        $file = $request->file('file');
        $disk = config('filesystems.default', 'r2');
        $path = $file->store("macroplans/{$macroplan->id}", $disk);

        MacroPlanAttachment::create([
            'macro_plan_id' => $macroplan->id,
            'filename'      => $file->getClientOriginalName(),
            'disk_path'     => $path,
            'disk'          => $disk,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'uploaded_by'   => Auth::id(),
        ]);

        return back()->with('success', 'Arquivo anexado.');
    }

    public function destroy(MacroPlan $macroplan, MacroPlanAttachment $attachment)
    {
        abort_unless($attachment->macro_plan_id === $macroplan->id, 403);

        Storage::disk($attachment->disk)->delete($attachment->disk_path);
        $attachment->delete();

        return back()->with('success', 'Anexo removido.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\MacroPlan;
use App\Models\MacroPlanAttachment;
use App\Services\MacroPlanDocumentService;
use App\Support\UploadOptions;
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
        $mimeType = $file->getMimeType();
        $path = $file->store("macroplans/{$macroplan->id}", UploadOptions::forStore($mimeType, $disk));

        MacroPlanAttachment::create([
            'macro_plan_id' => $macroplan->id,
            'filename'      => $file->getClientOriginalName(),
            'disk_path'     => $path,
            'disk'          => $disk,
            'mime_type'     => $mimeType,
            'size'          => $file->getSize(),
            'uploaded_by'   => Auth::id(),
        ]);

        return back()->with('success', 'Arquivo anexado.');
    }

    /**
     * Envia/troca o DOCUMENTO do planejamento (só HTML) — campo próprio, separado dos anexos.
     * A versão anterior continua na lista de anexos. Não recria campanhas/projetos: isso só
     * acontece ao criar o planejamento a partir do documento.
     */
    public function storeDocument(Request $request, MacroPlan $macroplan, MacroPlanDocumentService $documents)
    {
        $request->validate([
            'document' => 'required|file|mimes:html,htm|max:5120',
        ], [
            'document.mimes' => 'O documento do planejamento precisa ser o arquivo HTML gerado pela skill.',
        ]);

        $documents->setDocument($macroplan, $request->file('document'));

        return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => 'documento'])
            ->with('success', 'Documento do planejamento atualizado.');
    }

    public function destroy(MacroPlan $macroplan, MacroPlanAttachment $attachment)
    {
        abort_unless($attachment->macro_plan_id === $macroplan->id, 403);

        Storage::disk($attachment->disk)->delete($attachment->disk_path);
        $attachment->delete();

        return back()->with('success', 'Anexo removido.');
    }
}

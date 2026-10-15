<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Support\UploadOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProjectAttachmentController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $request->validate([
            'file' => 'required|file|max:20480', // 20 MB
        ]);

        $file = $request->file('file');
        $disk = config('filesystems.default', 'r2');
        $mimeType = $file->getMimeType();
        $path = $file->store("projects/{$project->id}", UploadOptions::forStore($mimeType, $disk));

        ProjectAttachment::create([
            'project_id' => $project->id,
            'filename'   => $file->getClientOriginalName(),
            'disk_path'  => $path,
            'disk'       => $disk,
            'mime_type'  => $mimeType,
            'size'       => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        return back()->with('success', 'Arquivo anexado.');
    }

    /**
     * Envia/troca o DOCUMENTO da campanha/projeto (só HTML) — campo próprio, separado dos
     * anexos; mesma dinâmica do planejamento. A versão anterior continua nos anexos.
     */
    public function storeDocument(Request $request, Project $project)
    {
        $request->validate([
            'document' => 'required|file|mimes:html,htm|max:5120',
        ], [
            'document.mimes' => 'O documento precisa ser o arquivo HTML gerado pela skill de campanha/projeto.',
        ]);

        $file = $request->file('document');
        $disk = config('filesystems.default', 'r2');
        $mimeType = $file->getMimeType();
        $path = $file->store("projects/{$project->id}", UploadOptions::forStore($mimeType, $disk));

        $attachment = ProjectAttachment::create([
            'project_id'  => $project->id,
            'filename'    => $file->getClientOriginalName(),
            'disk_path'   => $path,
            'disk'        => $disk,
            'mime_type'   => $mimeType,
            'size'        => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        $project->update(['document_attachment_id' => $attachment->id]);

        return back()->with('success', 'Documento atualizado.');
    }

    public function destroy(Project $project, ProjectAttachment $attachment)
    {
        abort_unless($attachment->project_id === $project->id, 403);

        Storage::disk($attachment->disk)->delete($attachment->disk_path);
        $attachment->delete();

        return back()->with('success', 'Anexo removido.');
    }
}

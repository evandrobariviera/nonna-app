<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Support\UploadOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ContractAttachmentController extends Controller
{
    public function store(Request $request, Contract $contract)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100 MB
            'kind' => 'nullable|in:proposta,assinado,aditivo,outro',
        ]);

        $file = $request->file('file');
        $disk = config('filesystems.default', 'r2');
        $mimeType = $file->getMimeType();
        $path = $file->store("contracts/{$contract->id}", UploadOptions::forStore($mimeType, $disk));

        ContractAttachment::create([
            'contract_id' => $contract->id,
            'kind'        => $request->input('kind', 'outro'),
            'filename'    => $file->getClientOriginalName(),
            'disk_path'   => $path,
            'disk'        => $disk,
            'mime_type'   => $mimeType,
            'size'        => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        return back()->with('success', 'Arquivo anexado.');
    }

    public function destroy(Contract $contract, ContractAttachment $attachment)
    {
        abort_unless($attachment->contract_id === $contract->id, 403);

        Storage::disk($attachment->disk)->delete($attachment->disk_path);
        $attachment->delete();

        return back()->with('success', 'Anexo removido.');
    }
}

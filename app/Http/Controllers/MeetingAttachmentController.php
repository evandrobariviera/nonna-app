<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MeetingAttachmentController extends Controller
{
    public function store(Request $request, Meeting $meeting)
    {
        $request->validate([
            'files'   => 'required|array|min:1',
            'files.*' => 'file|max:102400', // 100 MB
        ]);

        $disk = config('filesystems.default', 'r2');

        foreach ($request->file('files') as $file) {
            $path = $file->store("meetings/{$meeting->id}", $disk);

            MeetingAttachment::create([
                'meeting_id'  => $meeting->id,
                'filename'    => $file->getClientOriginalName(),
                'disk_path'   => $path,
                'disk'        => $disk,
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => Auth::id(),
            ]);
        }

        $label = count($request->file('files')) > 1 ? 'Arquivos anexados.' : 'Arquivo anexado.';

        return redirect()->route('meetings.show', $meeting)->with('success', $label);
    }

    public function destroy(Meeting $meeting, MeetingAttachment $attachment)
    {
        abort_unless($attachment->meeting_id === $meeting->id, 403);

        Storage::disk($attachment->disk)->delete($attachment->disk_path);
        $attachment->delete();

        return redirect()->route('meetings.show', $meeting)->with('success', 'Anexo removido.');
    }
}

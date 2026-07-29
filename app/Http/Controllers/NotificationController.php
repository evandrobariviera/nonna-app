<?php

namespace App\Http\Controllers;

use App\Models\InternalNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = InternalNotification::where('user_id', auth()->id())
            ->orderByDesc('generated_at')
            ->paginate(30);

        return view('notifications.index', compact('notifications'));
    }

    public function updateStatus(Request $request, InternalNotification $notification)
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $data = $request->validate([
            'status' => 'required|in:novo,lido,resolvido,descartado',
        ]);

        $notification->update($data);

        return back()->with('success', 'Notificação atualizada.');
    }
}

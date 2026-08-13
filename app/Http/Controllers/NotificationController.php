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

    // Polling leve pro aviso de navegador (Notification API + som) — só pega o que é
    // realmente novo desde o último cursor, pra não re-notificar o que já apareceu no
    // sino. "server_time" na resposta vira o próximo cursor no cliente, pra não
    // depender do relógio do navegador (ver resources/js/browser-notify.js).
    public function poll(Request $request)
    {
        $since = $request->query('since')
            ? \Carbon\Carbon::parse($request->query('since'))
            : now()->subMinute();

        $notifications = InternalNotification::where('user_id', auth()->id())
            ->where('status', 'novo')
            ->where('generated_at', '>', $since)
            ->orderBy('generated_at')
            ->limit(20)
            ->get(['id', 'title', 'body', 'link', 'generated_at']);

        return response()->json([
            'notifications' => $notifications,
            'server_time'   => now()->toISOString(),
        ]);
    }
}

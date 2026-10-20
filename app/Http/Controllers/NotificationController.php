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

        // Aviso de ATA: "lido" também tiraria da Dashboard (que só mostra "novo") sem confirmar.
        if (in_array($data['status'], ['descartado', 'lido'], true) && ! $notification->canDiscard()) {
            return back()->with('error', 'Este aviso só sai depois de confirmar "Li a ATA".');
        }

        $notification->update($data);

        return back()->with('success', 'Notificação atualizada.');
    }

    /**
     * Apagar o próprio aviso — cada pessoa limpa o seu sino (decisão do Evandro, 2026-10-20:
     * aviso é só informação, a pessoa controla a limpeza). "Li a ATA" não: é a confirmação
     * de leitura que a página da reunião mostra.
     */
    public function destroy(InternalNotification $notification)
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        if (! $notification->canDiscard()) {
            return back()->with('error', 'Este aviso só sai depois de confirmar "Li a ATA".');
        }

        $notification->delete();

        return back()->with('success', 'Aviso apagado.');
    }

    /** "Limpar os já vistos": apaga os meus avisos lidos/resolvidos/descartados (menos ATA). */
    public function clearSeen()
    {
        $n = InternalNotification::where('user_id', auth()->id())
            ->whereIn('status', ['lido', 'resolvido', 'descartado'])
            ->where(fn ($q) => $q->whereNull('kind')->orWhere('kind', '!=', InternalNotification::KIND_ATA))
            ->delete();

        return back()->with('success', $n ? "{$n} aviso(s) apagado(s)." : 'Nada pra limpar.');
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

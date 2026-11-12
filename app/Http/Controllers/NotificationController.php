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
            ->whereIn('status', ['lido', 'resolvido', 'descartado', 'expirado'])
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
            ->inBell()
            // Informativo não interrompe ninguém: fica no "Para saber" do sino.
            ->where(fn ($q) => $q->whereNull('kind')->orWhereNotIn('kind', InternalNotification::INFO_KINDS))
            ->where('generated_at', '>', $since)
            // Aviso juntado (repeat_count > 1) sobe no sino mas não toca popup/som de novo:
            // a pessoa já foi interrompida por esse item e ainda não abriu (atenção eficiente).
            ->where('repeat_count', 1)
            ->orderBy('generated_at')
            ->limit(20)
            ->get(['id', 'title', 'body', 'link', 'generated_at']);

        return response()->json([
            // Clique no popup passa por "abrir" (marca lido quando é conversa/informativo).
            'notifications' => $notifications->map(fn ($n) => [
                'id'           => $n->id,
                'title'        => $n->title,
                'body'         => $n->body,
                'link'         => $n->link ? route('notifications.open', $n) : null,
                'generated_at' => $n->generated_at,
            ]),
            'server_time'   => now()->toISOString(),
        ]);
    }

    /** "Marcar como vistos" de um bloco do sino (Conversas ou Para saber) — o que pede ação não entra. */
    public function markSeen(Request $request)
    {
        $kinds = \App\Support\NotificationPresenter::kindsOf((string) $request->input('group'));
        abort_if($kinds === [], 422);

        InternalNotification::where('user_id', auth()->id())
            ->where('status', 'novo')
            ->whereIn('kind', $kinds)
            ->update(['status' => 'lido']);

        return back();
    }

    /**
     * Abrir um aviso: leva pro link e, se é conversa/informativo/lembrete, já conta como lido
     * — sem precisar voltar no sino pra apertar botão (atenção com qualidade, 2026-11-12).
     * Aviso que pede ação (pendência, ajuste, Li a ATA) continua aceso até ser resolvido.
     * Aviso de outra pessoa (Dashboard em "Ver como") só redireciona, não mexe em nada.
     */
    public function open(InternalNotification $notification)
    {
        if ($notification->user_id === auth()->id()
            && $notification->status === 'novo'
            && $notification->isSeenOnOpen()) {
            $notification->update(['status' => 'lido']);
        }

        return redirect($notification->link ?: route('notifications.index'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\NotificationDispatch;
use Illuminate\Http\Request;

/**
 * Histórico de Envios das Mensagens Padrão (Configurações > Mensagens Padrão).
 * Só leitura — quem grava é o NotificationDispatchService.
 */
class NotificationDispatchController extends Controller
{
    public function index(Request $request)
    {
        $dispatches = $this->filteredDispatches($request);

        // Resumo dos últimos 7 dias — uma consulta agrupada só.
        $lastWeek = NotificationDispatch::where('created_at', '>=', now()->subDays(7))
            ->where('is_test', false)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('notification-dispatches.index', compact('dispatches', 'lastWeek'));
    }

    // Fragmento da listagem — chamado via fetch por live-filter.js.
    public function results(Request $request)
    {
        $dispatches = $this->filteredDispatches($request);

        return view('notification-dispatches._results', compact('dispatches'));
    }

    private function filteredDispatches(Request $request)
    {
        $query = NotificationDispatch::query()->latest();

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'ilike', "%{$search}%")
                  ->orWhere('contact_name', 'ilike', "%{$search}%")
                  ->orWhere('recipient', 'ilike', "%{$search}%");
            });
        }

        foreach (['status', 'type', 'channel'] as $field) {
            if ($value = $request->get($field)) {
                $query->where($field, $value);
            }
        }

        return $query->paginate(50)->withQueryString();
    }
}

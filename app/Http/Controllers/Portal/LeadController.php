<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClientLeadOpportunity;
use App\Models\ClientModule;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeadController extends Controller
{
    private const MODULE_KEY = 'central_leads';

    public function index()
    {
        $client = app('currentPortalClient');

        if ($client->moduleStatus(self::MODULE_KEY) !== 'ativo') {
            $module = ClientModule::where('client_id', $client->id)->where('module_key', self::MODULE_KEY)->first();

            return view('portal.leads.upsell', compact('client', 'module'));
        }

        $board = ClientLeadOpportunity::with(['lead', 'channel'])
            ->whereHas('lead', fn ($q) => $q->where('client_id', $client->id))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('stage');

        return view('portal.leads.index', compact('board', 'client'));
    }

    public function show(ClientLeadOpportunity $lead)
    {
        $client = app('currentPortalClient');
        abort_if($lead->lead->client_id !== $client->id, 403);

        $lead->load(['lead', 'channel', 'notes.user', 'notes.contact']);

        return view('portal.leads.show', ['opportunity' => $lead, 'client' => $client]);
    }

    public function storeNote(Request $request, ClientLeadOpportunity $lead)
    {
        $client = app('currentPortalClient');
        abort_if($lead->lead->client_id !== $client->id, 403);

        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $lead->notes()->create([
            'contact_id' => Auth::guard('portal')->id(),
            'body'       => $data['body'],
        ]);

        return back()->with('success', 'Nota adicionada.')->withFragment('notas');
    }

    public function updateStage(Request $request, ClientLeadOpportunity $lead)
    {
        $client = app('currentPortalClient');
        abort_if($lead->lead->client_id !== $client->id, 403);

        $data = $request->validate([
            'stage' => 'required|in:' . implode(',', array_keys(ClientLeadOpportunity::$stages)),
        ]);

        $fromStage = $lead->stage;
        $updates   = ['stage' => $data['stage']];
        if ($data['stage'] === 'ganho' && !$lead->won_at) {
            $updates['won_at'] = now();
        }
        if ($data['stage'] === 'perdido' && !$lead->lost_at) {
            $updates['lost_at'] = now();
        }

        $lead->update($updates);

        if ($fromStage !== $data['stage']) {
            $lead->logStageChange($fromStage, $data['stage'], null, Auth::guard('portal')->id());
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Estágio atualizado.');
    }

    // Vitrine → "Quero contratar": não ativa nada sozinho (decisão de venda
    // continua manual do lado Nonna) — só registra o pedido e avisa a
    // Direção Geral, que decide e ativa o módulo pra esse Cliente.
    public function requestModule(NotificationService $notifications)
    {
        $client = app('currentPortalClient');

        $module = ClientModule::firstOrCreate(
            ['client_id' => $client->id, 'module_key' => self::MODULE_KEY],
            ['status' => 'nao_contratado']
        );

        if ($module->status !== 'ativo') {
            $module->update(['requested_at' => now()]);

            $users = User::whereHas('functionalRoles', fn ($q) => $q->where('key', 'direcao_geral'))->get();
            $notifications->notifyUsers(
                $users,
                'modulo_solicitado',
                "Pedido de contratação — {$module->moduleLabel()}",
                "{$client->displayName()} quer contratar o módulo \"{$module->moduleLabel()}\".",
                route('leads.index', ['client_id' => $client->id]),
                $module
            );
        }

        return back()->with('success', 'Pedido enviado! Nosso time vai entrar em contato.');
    }
}

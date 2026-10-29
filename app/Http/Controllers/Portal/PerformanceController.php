<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LeadController as InternalLeads;
use App\Models\CampaignInsight;
use App\Models\ClientModule;
use App\Models\ServiceDiagnostic;
use App\Models\User;
use App\Services\CampaignInsightService;
use App\Services\Leads\LeadFunnel;
use App\Services\Performance\PerformanceDashboard;
use App\Services\SystemNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Central de Performance no Portal: dashboard e aba Insights, cada parte só
 * com o módulo liberado (Client::portalModuleActive); o resto vira vitrine.
 * As abas Campanhas, Leads e Atendimento são as telas do Portal que já
 * existiam, com a barra portal.performance._tabs em cima.
 */
class PerformanceController extends Controller
{
    public function index(Request $request)
    {
        $client = app('currentPortalClient');
        [$from, $to] = InternalLeads::period($request);

        $active = collect(array_keys(ClientModule::$modules))->mapWithKeys(fn ($k) => [$k => $client->portalModuleActive($k)]);
        // Com "lead atribuído some para os outros", só o contato principal vê os números somados dos leads.
        $seesLeads = $active['central_leads']
            && (!ClientModule::leadsHideAssigned($client->id) || $client->isPrimaryContact(Auth::guard('portal')->id()));

        return view('portal.performance.index', [
            'client'        => $client,
            'active'        => $active,
            'seesLeads'     => $seesLeads,
            'from'          => $from,
            'to'            => $to,
            'funnel'        => $active['trafego'] || $seesLeads ? LeadFunnel::forClient($client, $from, $to) : null,
            'weekly'        => $active['trafego'] || $seesLeads ? PerformanceDashboard::weekly($client) : null,
            'conversations' => $active['atendimento'] ? PerformanceDashboard::conversations($client, $from, $to) : null,
            'diagnostic'    => $active['atendimento'] ? $this->latestPublishedDiagnostic($client->id) : null,
            'insights'      => $active['analises_ia'] ? $this->clientInsights($client->id)->limit(3)->get() : collect(),
        ]);
    }

    public function insights()
    {
        $client = app('currentPortalClient');
        if (!$client->portalModuleActive('analises_ia')) {
            return $this->showcase('analises_ia');
        }

        return view('portal.performance.insights', [
            'client'   => $client,
            'insights' => $this->clientInsights($client->id)->paginate(20),
        ]);
    }

    // Vitrine de um módulo não contratado.
    public function showcase(string $moduleKey)
    {
        abort_unless(array_key_exists($moduleKey, ClientModule::$modules), 404);
        $client = app('currentPortalClient');

        if ($client->portalModuleActive($moduleKey)) {
            return redirect()->route('portal.performance.index');
        }

        return view('portal.performance.showcase', [
            'client'    => $client,
            'moduleKey' => $moduleKey,
            'module'    => ClientModule::where('client_id', $client->id)->where('module_key', $moduleKey)->first(),
        ]);
    }

    // "Quero contratar": não ativa nada sozinho — registra o pedido e avisa a
    // Direção Geral, que decide e libera o módulo na ficha do cliente.
    public function requestModule(string $moduleKey)
    {
        abort_unless(array_key_exists($moduleKey, ClientModule::$modules), 404);
        $client = app('currentPortalClient');

        // Já liberado (inclusive pela regra automática): nada a pedir — e criar
        // a linha "nao_contratado" aqui desligaria o módulo.
        if ($client->portalModuleActive($moduleKey)) {
            return redirect()->route('portal.performance.index');
        }

        $module = ClientModule::firstOrCreate(
            ['client_id' => $client->id, 'module_key' => $moduleKey],
            ['status' => 'nao_contratado']
        );

        if ($module->status !== 'ativo') {
            $module->update(['requested_at' => now()]);

            app(SystemNotificationService::class)->send(
                'portal.modulo_solicitado',
                User::whereHas('functionalRoles', fn ($q) => $q->where('key', 'direcao_geral'))->get(),
                ['client_name' => $client->displayName(), 'module_label' => $module->moduleLabel()],
                route('clients.show', [$client, 'tab' => 'portal']),
                $module,
                $client->organization_id,
            );
        }

        return back()->with('success', 'Pedido enviado! Nosso time vai entrar em contato.');
    }

    // Só o que é seguro mostrar ao cliente: kinds liberados e com resumo em
    // linguagem de cliente (mesmo filtro de "Como está indo" nas campanhas).
    private function clientInsights(string $clientId)
    {
        return CampaignInsight::where('client_id', $clientId)
            ->whereIn('kind', CampaignInsightService::CLIENT_VISIBLE_KINDS)
            ->whereIn('status', ['novo', 'lido', 'resolvido'])
            ->whereNotNull('client_summary')
            ->where('generated_at', '>=', now()->subDays(60))
            ->with('campaign:id,name')
            ->orderByDesc('generated_at');
    }

    private function latestPublishedDiagnostic(string $clientId): ?ServiceDiagnostic
    {
        return ServiceDiagnostic::where('client_id', $clientId)
            ->where('status', 'published')
            ->orderByDesc('period_end')
            ->first();
    }
}

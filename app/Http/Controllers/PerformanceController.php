<?php

namespace App\Http\Controllers;

use App\Models\CampaignInsight;
use App\Models\Client;
use App\Services\Leads\LeadFunnel;
use App\Services\Performance\PerformanceDashboard;
use Illuminate\Http\Request;

/**
 * Central de Performance: o dashboard central e a aba Insights. As outras
 * abas (Leads, Campanhas, Atendimento) são as telas que já existiam, com a
 * barra de abas (performance._tabs) em cima.
 */
class PerformanceController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = LeadController::period($request);
        $client = self::selectedClient($request);

        $data = [
            'client'        => $client,
            'clients'       => self::clientOptions(),
            'from'          => $from,
            'to'            => $to,
            'weekly'        => PerformanceDashboard::weekly($client),
            'conversations' => PerformanceDashboard::conversations($client, $from, $to),
            'insights'      => PerformanceDashboard::openInsights($client),
            'insightsCount' => PerformanceDashboard::openInsightsCount($client),
        ];

        if ($client) {
            $data['funnel'] = LeadFunnel::forClient($client, $from, $to);
            $data['diagnostic'] = PerformanceDashboard::latestServiceDiagnostic($client);
        } else {
            $data['overview'] = LeadFunnel::overview($from, $to);
        }

        return view('performance.index', $data);
    }

    // Aba Insights: alertas automáticos e análises dos agentes de IA, de
    // todas as campanhas, num lugar só (antes ficavam dentro de Campanhas).
    public function insights(Request $request)
    {
        $client = self::selectedClient($request);
        $tipo = $request->input('tipo');
        $status = $request->input('status', 'abertos');

        $insights = CampaignInsight::query()
            ->when($client, fn ($q) => $q->where('client_id', $client->id))
            ->when($tipo === 'analises', fn ($q) => $q->where('kind', 'lead_funnel'))
            ->when($tipo === 'alertas', fn ($q) => $q->where('kind', '!=', 'lead_funnel'))
            ->when($status === 'abertos', fn ($q) => $q->whereIn('status', ['novo', 'lido']))
            ->when($status === 'fechados', fn ($q) => $q->whereIn('status', ['resolvido', 'descartado']))
            ->with(['client', 'campaign', 'adset', 'ad'])
            ->orderByRaw("CASE WHEN status IN ('novo', 'lido') THEN 0 ELSE 1 END")
            ->orderByDesc('generated_at')
            ->paginate(30)
            ->withQueryString();

        return view('performance.insights', [
            'client'   => $client,
            'clients'  => self::clientOptions(),
            'insights' => $insights,
            'tipo'     => $tipo,
            'status'   => $status,
        ]);
    }

    // Aba Site: o mesmo Relatório Analítico que o cliente vê no Portal (GA4 +
    // Search Console). Sem cliente escolhido, lista quem já tem os números ligados.
    public function site(Request $request)
    {
        $client = self::selectedClient($request);
        $days = (int) $request->query('dias', 28);
        if (!array_key_exists($days, Portal\SiteReportController::PERIODS)) {
            $days = 28;
        }

        $data = ['client' => $client, 'clients' => self::clientOptions(), 'days' => $days, 'tracking' => null];

        if ($client) {
            $tracking = \App\Services\Tracking\SiteReports::tracking($client);
            $reports = \App\Services\Tracking\SiteReports::for($client);
            $data['tracking'] = $tracking;
            $data['ga4'] = $tracking?->ga4_property_id ? $reports->ga4Summary($tracking->ga4_property_id, $days) : null;
            $data['daily'] = $tracking?->ga4_property_id ? $reports->ga4Daily($tracking->ga4_property_id, $days) : null;
            $data['gsc'] = $tracking?->search_console_site ? $reports->searchConsoleSummary($tracking->search_console_site, $days) : null;
        } else {
            $linked = \App\Models\ClientTracking::where(fn ($q) => $q->whereNotNull('ga4_property_id')->orWhereNotNull('search_console_site'))->pluck('client_id');
            $data['linkedClients'] = Client::whereIn('id', $linked)->where('status', '!=', 'inactive')
                ->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
        }

        return view('performance.site', $data);
    }

    public static function selectedClient(Request $request): ?Client
    {
        return $request->filled('client_id') ? Client::find($request->client_id) : null;
    }

    public static function clientOptions()
    {
        return Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
    }
}

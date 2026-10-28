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

    public static function selectedClient(Request $request): ?Client
    {
        return $request->filled('client_id') ? Client::find($request->client_id) : null;
    }

    public static function clientOptions()
    {
        return Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
    }
}

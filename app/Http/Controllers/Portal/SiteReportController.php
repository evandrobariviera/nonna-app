<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Tracking\SiteReports;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Relatório Analítico no Portal: números do site do cliente (Google Analytics +
 * Search Console), lidos do Google na hora (com cache de 6h). Aparece para quem tem
 * Analytics ou Search Console vinculado na aba Rastreamento da ficha do cliente.
 */
class SiteReportController extends Controller
{
    public const PERIODS = [7 => 'Últimos 7 dias', 28 => 'Últimos 28 dias', 90 => 'Últimos 90 dias'];

    public function index(Request $request): View
    {
        $client = app('currentPortalClient');
        $tracking = SiteReports::tracking($client);
        abort_unless($tracking, 404);

        $days = (int) $request->query('dias', 28);
        if (!array_key_exists($days, self::PERIODS)) {
            $days = 28;
        }

        $reports = SiteReports::for($client);
        $ga4 = $tracking->ga4_property_id ? $reports->ga4Summary($tracking->ga4_property_id, $days) : null;
        $daily = $tracking->ga4_property_id ? $reports->ga4Daily($tracking->ga4_property_id, $days) : null;
        $gsc = $tracking->search_console_site ? $reports->searchConsoleSummary($tracking->search_console_site, $days) : null;

        return view('portal.site-report.index', compact('client', 'days', 'ga4', 'daily', 'gsc'));
    }
}

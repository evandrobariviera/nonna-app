<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClientIntegration;
use App\Models\ServiceDiagnostic;
use Illuminate\View\View;

class ServiceDiagnosticController extends Controller
{
    public function index(): View
    {
        $client = app('currentPortalClient');

        $integrations = $client->integrations()
            ->withCount(['diagnostics as published_diagnostics_count' => fn ($q) => $q->where('status', 'published')])
            ->get();

        $diagnostics = ServiceDiagnostic::whereIn('client_integration_id', $integrations->pluck('id'))
            ->where('status', 'published')
            ->with('integration')
            ->orderBy('version')
            ->get();

        return view('portal.service-diagnostics.index', compact('client', 'integrations', 'diagnostics'));
    }

    public function integration(ClientIntegration $integration): View
    {
        $client = app('currentPortalClient');
        abort_unless($integration->client_id === $client->id, 403);

        $diagnostics = $integration->diagnostics()->where('status', 'published')->orderBy('version')->get();

        return view('portal.service-diagnostics.integration', compact('client', 'integration', 'diagnostics'));
    }

    public function show(ClientIntegration $integration, ServiceDiagnostic $diagnostic): View
    {
        $client = app('currentPortalClient');

        abort_unless($integration->client_id === $client->id, 403);
        abort_unless($diagnostic->client_integration_id === $integration->id, 404);
        abort_unless($diagnostic->status === 'published', 404);

        $diagnostic->load('personas', 'recommendations', 'integration');

        return view('portal.service-diagnostics.show', compact('client', 'integration', 'diagnostic'));
    }
}

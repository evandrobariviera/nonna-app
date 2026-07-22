<?php

namespace App\Http\Controllers;

use App\Models\ClientIntegration;
use App\Models\ServiceDiagnostic;

class ServiceDiagnosticController extends Controller
{
    public function index()
    {
        $integrations = ClientIntegration::with('client')
            ->withCount('diagnostics')
            ->get()
            ->groupBy(fn ($integration) => $integration->client->company_name);

        return view('service-diagnostics.index', compact('integrations'));
    }

    public function integration(ClientIntegration $integration)
    {
        $integration->load('client');
        $diagnostics = $integration->diagnostics()->orderBy('version')->get();

        return view('service-diagnostics.integration', compact('integration', 'diagnostics'));
    }

    public function show(ClientIntegration $integration, ServiceDiagnostic $diagnostic)
    {
        abort_unless($diagnostic->client_integration_id === $integration->id, 404);

        $integration->load('client');
        $diagnostic->load('personas', 'aiAgent', 'recommendations');

        return view('service-diagnostics.show', compact('integration', 'diagnostic'));
    }
}

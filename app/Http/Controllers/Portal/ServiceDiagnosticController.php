<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ServiceDiagnostic;
use Illuminate\View\View;

class ServiceDiagnosticController extends Controller
{
    public function index(): View
    {
        $client = app('currentPortalClient');

        $diagnostics = ServiceDiagnostic::whereIn('client_integration_id', $client->integrations()->pluck('id'))
            ->where('status', 'published')
            ->with('integration')
            ->orderBy('version')
            ->get();

        return view('portal.service-diagnostics.index', compact('client', 'diagnostics'));
    }

    public function show(ServiceDiagnostic $diagnostic): View
    {
        $client = app('currentPortalClient');

        abort_unless($diagnostic->client_id === $client->id, 403);
        abort_unless($diagnostic->status === 'published', 404);

        $diagnostic->load('personas', 'recommendations', 'integration');

        return view('portal.service-diagnostics.show', compact('client', 'diagnostic'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ClientIntegration;
use App\Models\ServiceConversation;
use App\Models\ServiceDiagnostic;
use App\Models\ServiceMessage;
use App\Services\ServiceDiagnostics\ServiceDiagnosticGenerator;
use Illuminate\Http\RedirectResponse;

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

    public function integration(ClientIntegration $integration, ServiceDiagnosticGenerator $generator)
    {
        $integration->load('client');
        $diagnostics = $integration->diagnostics()->orderBy('version')->get();

        $readiness = $this->buildReadiness($integration, $generator);

        return view('service-diagnostics.integration', compact('integration', 'diagnostics', 'readiness'));
    }

    private function buildReadiness(ClientIntegration $integration, ServiceDiagnosticGenerator $generator): array
    {
        [$periodStart, $periodEnd] = $generator->resolvePeriod($integration);

        $conversationsQuery = ServiceConversation::where('client_integration_id', $integration->id)
            ->where('is_group', false);

        $pendingConversations = (clone $conversationsQuery)
            ->whereHas('messages', fn ($q) => $q->whereBetween('sent_at', [$periodStart, $periodEnd]))
            ->count();

        $pendingMessages = ServiceMessage::whereHas('conversation', fn ($q) => $q
                ->where('client_integration_id', $integration->id)
                ->where('is_group', false))
            ->whereBetween('sent_at', [$periodStart, $periodEnd])
            ->count();

        return [
            'period_start'          => $periodStart,
            'period_end'            => $periodEnd,
            'total_conversations'   => (clone $conversationsQuery)->count(),
            'total_messages'        => ServiceMessage::whereHas('conversation', fn ($q) => $q
                    ->where('client_integration_id', $integration->id)
                    ->where('is_group', false))
                ->count(),
            'last_message_at'       => (clone $conversationsQuery)->max('last_message_at'),
            'pending_conversations' => $pendingConversations,
            'pending_messages'      => $pendingMessages,
        ];
    }

    public function show(ClientIntegration $integration, ServiceDiagnostic $diagnostic)
    {
        abort_unless($diagnostic->client_integration_id === $integration->id, 404);

        $integration->load('client');
        $diagnostic->load('personas', 'aiAgent', 'recommendations');

        return view('service-diagnostics.show', compact('integration', 'diagnostic'));
    }

    public function print(ClientIntegration $integration, ServiceDiagnostic $diagnostic)
    {
        abort_unless($diagnostic->client_integration_id === $integration->id, 404);

        $integration->load('client');
        $diagnostic->load('personas', 'aiAgent', 'recommendations');

        return view('service-diagnostics.print', compact('integration', 'diagnostic'));
    }

    public function generate(ClientIntegration $integration, ServiceDiagnosticGenerator $generator): RedirectResponse
    {
        try {
            $diagnostic = $generator->generate($integration, 'manual');

            return redirect()->route('service-diagnostics.show', [$integration, $diagnostic])
                ->with('success', 'Diagnóstico gerado com sucesso.');
        } catch (\Throwable $e) {
            return redirect()->route('service-diagnostics.integration', $integration)
                ->with('error', 'Falha ao gerar diagnóstico: ' . $e->getMessage());
        }
    }

    public function publish(ClientIntegration $integration, ServiceDiagnostic $diagnostic): RedirectResponse
    {
        abort_unless($diagnostic->client_integration_id === $integration->id, 404);

        $diagnostic->update(['status' => 'published', 'published_at' => now()]);

        return redirect()->route('service-diagnostics.show', [$integration, $diagnostic])
            ->with('success', 'Diagnóstico publicado - já está visível pro cliente no Portal.');
    }
}

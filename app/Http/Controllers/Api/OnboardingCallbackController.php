<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientOnboarding;
use App\Services\ClientOnboardingService;
use Illuminate\Http\Request;

/**
 * Callback do n8n confirmando que um passo externo da esteira de onboarding
 * rodou (grupo WhatsApp criado, pasta no Drive, contrato gerado no Docs).
 * Autenticado por token Sanctum da Organização (ver routes/api.php).
 */
class OnboardingCallbackController extends Controller
{
    public function step(Request $request, Client $client, ClientOnboardingService $service)
    {
        $data = $request->validate([
            'step'         => 'required|string|in:' . implode(',', array_keys(ClientOnboarding::$checklistLabels)),
            'contract_url' => 'nullable|url|max:500',
        ]);

        $ok = $service->confirmStep($client, $data['step'], $data['contract_url'] ?? null);

        return response()->json([
            'ok'   => $ok,
            'step' => $data['step'],
        ], $ok ? 200 : 422);
    }
}

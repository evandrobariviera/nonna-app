<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationIntegration;
use Illuminate\Http\JsonResponse;

class IntegrationCredentialController extends Controller
{
    // GET /api/integrations/{provider}
    // n8n busca as credenciais da organização (do token) para chamar a API externa diretamente.
    public function show(string $provider): JsonResponse
    {
        $org = app('currentOrganization');

        $integration = OrganizationIntegration::where('organization_id', $org->id)
            ->where('provider', $provider)
            ->where('status', 'connected')
            ->first();

        if (!$integration || !$integration->hasCredentials()) {
            return response()->json([
                'message' => "Nenhuma integração conectada com credenciais para o provider '{$provider}'.",
            ], 404);
        }

        return response()->json([
            'provider'         => $integration->provider,
            'label'            => $integration->label,
            'external_id'      => $integration->external_id,
            'credentials'      => $integration->credentials,
            'expires_at'       => $integration->expires_at?->toIso8601String(),
            'last_verified_at' => $integration->last_verified_at?->toIso8601String(),
        ]);
    }
}

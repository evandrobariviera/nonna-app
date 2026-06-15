<?php

namespace App\Http\Controllers;

use App\Models\OrganizationIntegration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationIntegrationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $org = app('currentOrganization');

        $data = $request->validate([
            'provider'    => ['required', 'string', 'in:' . implode(',', array_keys(OrganizationIntegration::$providers))],
            'label'       => ['required', 'string', 'max:120'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'status'      => ['required', 'string', 'in:pending,connected,disconnected,error'],
        ]);

        $org->integrations()->create($data);

        return back()->with('success', 'Integração adicionada.')->with('tab', 'integracoes');
    }

    public function update(Request $request, OrganizationIntegration $integration): RedirectResponse
    {
        $this->authorizeIntegration($integration);

        $data = $request->validate([
            'provider'    => ['required', 'string', 'in:' . implode(',', array_keys(OrganizationIntegration::$providers))],
            'label'       => ['required', 'string', 'max:120'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'status'      => ['required', 'string', 'in:pending,connected,disconnected,error'],
        ]);

        $integration->update($data);

        return back()->with('success', 'Integração atualizada.')->with('tab', 'integracoes');
    }

    public function destroy(OrganizationIntegration $integration): RedirectResponse
    {
        $this->authorizeIntegration($integration);
        $integration->delete();

        return back()->with('success', 'Integração removida.')->with('tab', 'integracoes');
    }

    private function authorizeIntegration(OrganizationIntegration $integration): void
    {
        $org = app('currentOrganization');
        abort_unless($integration->organization_id === $org->id, 403);
    }
}

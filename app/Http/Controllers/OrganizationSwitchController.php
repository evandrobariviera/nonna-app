<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Seletor de organização no topo (só aparece pra quem participa de mais de uma,
 * ex.: Nonna e a organização de demonstração do Portal). A escolha fica na sessão
 * e o SetTenant usa ela nas próximas telas.
 */
class OrganizationSwitchController extends Controller
{
    public function __invoke(Request $request, Organization $organization): RedirectResponse
    {
        $member = $request->user()->organizations()
            ->where('organizations.id', $organization->id)
            ->whereIn('organizations.status', ['active', 'trial', 'demo'])
            ->exists();

        abort_unless($member, 403);

        $request->session()->put('current_organization_id', $organization->id);
        // Filtros guardados apontam para clientes da organização anterior.
        $request->session()->forget([\App\Http\Middleware\RememberCampaignClient::KEY, 'approval_review']);

        return redirect()->route('dashboard')->with('success', "Você está em: {$organization->name}");
    }
}

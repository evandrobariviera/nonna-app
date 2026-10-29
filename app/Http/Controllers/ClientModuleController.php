<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientModuleController extends Controller
{
    public function enable(Client $client, string $moduleKey): RedirectResponse
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);
        abort_unless(array_key_exists($moduleKey, ClientModule::$modules), 404);

        ClientModule::updateOrCreate(
            ['client_id' => $client->id, 'module_key' => $moduleKey],
            ['status' => 'ativo', 'enabled_at' => now(), 'enabled_by' => Auth::id()],
        );

        return back()->with('success', 'Módulo liberado — já aparece ativo pro cliente no Portal.');
    }

    // Hoje só a Central de Leads tem configuração: "lead atribuído some para os outros".
    public function updateSettings(Request $request, Client $client, string $moduleKey): RedirectResponse
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);
        abort_unless($moduleKey === 'central_leads', 404);

        $module = ClientModule::where('client_id', $client->id)->where('module_key', $moduleKey)->firstOrFail();
        $module->update(['settings' => array_merge($module->settings ?? [], ['hide_assigned' => $request->boolean('hide_assigned')])]);

        return back()->with('success', 'Configuração da Central de Leads salva.');
    }

    public function disable(Client $client, string $moduleKey): RedirectResponse
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);
        abort_unless(array_key_exists($moduleKey, ClientModule::$modules), 404);

        // updateOrCreate: módulo ligado só pela regra automática (sem linha)
        // também precisa poder ser desligado.
        ClientModule::updateOrCreate(
            ['client_id' => $client->id, 'module_key' => $moduleKey],
            ['status' => 'nao_contratado', 'enabled_at' => null, 'enabled_by' => null],
        );

        return back()->with('success', 'Módulo desativado.');
    }
}

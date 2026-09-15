<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientModule;
use Illuminate\Http\RedirectResponse;
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

    public function disable(Client $client, string $moduleKey): RedirectResponse
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);
        abort_unless(array_key_exists($moduleKey, ClientModule::$modules), 404);

        ClientModule::where('client_id', $client->id)
            ->where('module_key', $moduleKey)
            ->update(['status' => 'nao_contratado', 'enabled_at' => null, 'enabled_by' => null]);

        return back()->with('success', 'Módulo desativado.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\FunctionalRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FunctionalRoleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $org = app('currentOrganization');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $key = $this->uniqueKey($org->id, Str::slug($data['name'], '_'));

        FunctionalRole::create([
            'organization_id' => $org->id,
            'key'             => $key,
            'name'            => $data['name'],
            'is_protected'    => false,
        ]);

        return back()->with('success', 'Papel funcional criado.')->with('tab', 'papeis');
    }

    public function update(Request $request, FunctionalRole $functionalRole): RedirectResponse
    {
        $org = app('currentOrganization');
        abort_unless($functionalRole->organization_id === $org->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        // A chave nunca muda depois de criada — é o que Automações e as seções do Dashboard
        // usam pra identificar o papel; só o rótulo (name) é editável.
        $functionalRole->update(['name' => $data['name']]);

        return back()->with('success', 'Papel funcional atualizado.')->with('tab', 'papeis');
    }

    public function destroy(FunctionalRole $functionalRole): RedirectResponse
    {
        $org = app('currentOrganization');
        abort_unless($functionalRole->organization_id === $org->id, 403);

        abort_if(
            $functionalRole->is_protected,
            403,
            'Esse papel é usado por uma seção do sistema e não pode ser removido.'
        );

        $functionalRole->delete();

        return back()->with('success', 'Papel funcional removido.')->with('tab', 'papeis');
    }

    private function uniqueKey(string $organizationId, string $baseKey): string
    {
        $key = $baseKey;
        $suffix = 2;

        while (FunctionalRole::where('organization_id', $organizationId)->where('key', $key)->exists()) {
            $key = "{$baseKey}_{$suffix}";
            $suffix++;
        }

        return $key;
    }
}

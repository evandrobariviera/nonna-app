<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SectorController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $sector = Sector::create(['name' => $data['name']]);
        $sector->users()->sync($data['user_ids'] ?? []);

        return back()->with('success', 'Setor criado.')->with('tab', 'setores');
    }

    public function update(Request $request, Sector $sector): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $sector->update(['name' => $data['name']]);
        $sector->users()->sync($data['user_ids'] ?? []);

        return back()->with('success', 'Setor atualizado.')->with('tab', 'setores');
    }

    public function destroy(Sector $sector): RedirectResponse
    {
        $sector->delete();

        return back()->with('success', 'Setor removido.')->with('tab', 'setores');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\BrandDossier;
use App\Models\Client;
use App\Models\DossierPersona;
use Illuminate\Http\Request;

class DossierPersonaController extends Controller
{
    public function store(Request $request, Client $client, BrandDossier $dossier)
    {
        $validated = $request->validate([
            'tipo'               => 'required|in:principal,secundaria',
            'nome_ficticio'      => 'nullable|string|max:100',
            'idade_genero'       => 'nullable|string|max:100',
            'cargo_setor'        => 'nullable|string|max:200',
            'renda_contexto'     => 'nullable|string|max:200',
            'como_se_informa'    => 'nullable|string',
            'o_que_valida_compra' => 'nullable|string',
            'dores_principais'   => 'nullable|string',
            'o_que_motiva'       => 'nullable|string',
            'o_que_nunca_diria'  => 'nullable|string',
            'insight_persona'    => 'nullable|string',
        ]);

        $position = $dossier->personas()->max('position') + 1;

        $persona = $dossier->personas()->create(array_merge($validated, ['position' => $position]));

        return response()->json($persona);
    }

    public function update(Request $request, Client $client, BrandDossier $dossier, DossierPersona $persona)
    {
        $validated = $request->validate([
            'tipo'               => 'sometimes|required|in:principal,secundaria',
            'nome_ficticio'      => 'nullable|string|max:100',
            'idade_genero'       => 'nullable|string|max:100',
            'cargo_setor'        => 'nullable|string|max:200',
            'renda_contexto'     => 'nullable|string|max:200',
            'como_se_informa'    => 'nullable|string',
            'o_que_valida_compra' => 'nullable|string',
            'dores_principais'   => 'nullable|string',
            'o_que_motiva'       => 'nullable|string',
            'o_que_nunca_diria'  => 'nullable|string',
            'insight_persona'    => 'nullable|string',
        ]);

        $persona->update($validated);

        return response()->json(['ok' => true]);
    }

    public function destroy(Client $client, BrandDossier $dossier, DossierPersona $persona)
    {
        $persona->delete();

        return response()->json(['ok' => true]);
    }
}

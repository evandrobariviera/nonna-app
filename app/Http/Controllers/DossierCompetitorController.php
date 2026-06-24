<?php

namespace App\Http\Controllers;

use App\Models\BrandDossier;
use App\Models\Client;
use App\Models\DossierCompetitor;
use Illuminate\Http\Request;

class DossierCompetitorController extends Controller
{
    public function store(Request $request, Client $client, BrandDossier $dossier)
    {
        $validated = $request->validate([
            'nome'               => 'required|string|max:200',
            'o_que_comunica'     => 'nullable|string',
            'como_se_posiciona'  => 'nullable|string',
            'o_que_nao_ocupa'    => 'nullable|string',
        ]);

        $position = $dossier->competitors()->max('position') + 1;

        $competitor = $dossier->competitors()->create(array_merge($validated, ['position' => $position]));

        return response()->json($competitor);
    }

    public function update(Request $request, Client $client, BrandDossier $dossier, DossierCompetitor $competitor)
    {
        $validated = $request->validate([
            'nome'               => 'sometimes|required|string|max:200',
            'o_que_comunica'     => 'nullable|string',
            'como_se_posiciona'  => 'nullable|string',
            'o_que_nao_ocupa'    => 'nullable|string',
        ]);

        $competitor->update($validated);

        return response()->json(['ok' => true]);
    }

    public function destroy(Client $client, BrandDossier $dossier, DossierCompetitor $competitor)
    {
        $competitor->delete();

        return response()->json(['ok' => true]);
    }
}

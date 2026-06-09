<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientDiagnostic;
use App\Models\DiagnosticCompetitor;
use Illuminate\Http\Request;

class DiagnosticCompetitorController extends Controller
{
    public function store(Request $request, Client $client, ClientDiagnostic $diagnostic)
    {
        abort_unless($diagnostic->client_id === $client->id, 403);

        $data = $request->validate([
            'name'          => 'required|string|max:200',
            'main_channels' => 'nullable|string|max:200',
            'positioning'   => 'nullable|string',
            'strengths'     => 'nullable|string',
            'vulnerability' => 'nullable|string',
        ]);

        $data['diagnostic_id'] = $diagnostic->id;
        $data['position']      = $diagnostic->competitors()->max('position') + 1;

        DiagnosticCompetitor::create($data);

        return redirect()->route('clients.diagnostics.edit', [$client, $diagnostic, 'sec' => 'sec04'])
            ->with('success', 'Concorrente adicionado.');
    }

    public function update(Request $request, Client $client, ClientDiagnostic $diagnostic, DiagnosticCompetitor $competitor)
    {
        abort_unless($diagnostic->client_id === $client->id, 403);
        abort_unless($competitor->diagnostic_id === $diagnostic->id, 403);

        $data = $request->validate([
            'name'          => 'required|string|max:200',
            'main_channels' => 'nullable|string|max:200',
            'positioning'   => 'nullable|string',
            'strengths'     => 'nullable|string',
            'vulnerability' => 'nullable|string',
        ]);

        $competitor->update($data);

        return redirect()->route('clients.diagnostics.edit', [$client, $diagnostic, 'sec' => 'sec04'])
            ->with('success', 'Concorrente atualizado.');
    }

    public function destroy(Client $client, ClientDiagnostic $diagnostic, DiagnosticCompetitor $competitor)
    {
        abort_unless($diagnostic->client_id === $client->id, 403);
        abort_unless($competitor->diagnostic_id === $diagnostic->id, 403);

        $competitor->delete();

        return redirect()->route('clients.diagnostics.edit', [$client, $diagnostic, 'sec' => 'sec04'])
            ->with('success', 'Concorrente removido.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientDiagnostic;
use App\Models\DiagnosticPersona;
use Illuminate\Http\Request;

class DiagnosticPersonaController extends Controller
{
    public function store(Request $request, Client $client, ClientDiagnostic $diagnostic)
    {
        abort_unless($diagnostic->client_id === $client->id, 403);

        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'age_range'       => 'nullable|string|max:50',
            'profession'      => 'nullable|string|max:150',
            'income'          => 'nullable|string|max:100',
            'location'        => 'nullable|string|max:150',
            'what_they_seek'  => 'nullable|string',
            'frustrations'    => 'nullable|string',
            'decision_process' => 'nullable|string',
            'objections'      => 'nullable|string',
        ]);

        $data['diagnostic_id'] = $diagnostic->id;
        $data['position']      = $diagnostic->personas()->max('position') + 1;

        DiagnosticPersona::create($data);

        return redirect()->route('clients.diagnostics.edit', [$client, $diagnostic, 'sec' => 'sec05'])
            ->with('success', 'Persona adicionada.');
    }

    public function update(Request $request, Client $client, ClientDiagnostic $diagnostic, DiagnosticPersona $persona)
    {
        abort_unless($diagnostic->client_id === $client->id, 403);
        abort_unless($persona->diagnostic_id === $diagnostic->id, 403);

        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'age_range'       => 'nullable|string|max:50',
            'profession'      => 'nullable|string|max:150',
            'income'          => 'nullable|string|max:100',
            'location'        => 'nullable|string|max:150',
            'what_they_seek'  => 'nullable|string',
            'frustrations'    => 'nullable|string',
            'decision_process' => 'nullable|string',
            'objections'      => 'nullable|string',
        ]);

        $persona->update($data);

        return redirect()->route('clients.diagnostics.edit', [$client, $diagnostic, 'sec' => 'sec05'])
            ->with('success', 'Persona atualizada.');
    }

    public function destroy(Client $client, ClientDiagnostic $diagnostic, DiagnosticPersona $persona)
    {
        abort_unless($diagnostic->client_id === $client->id, 403);
        abort_unless($persona->diagnostic_id === $diagnostic->id, 403);

        $persona->delete();

        return redirect()->route('clients.diagnostics.edit', [$client, $diagnostic, 'sec' => 'sec05'])
            ->with('success', 'Persona removida.');
    }
}

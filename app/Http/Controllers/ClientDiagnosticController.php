<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientDiagnostic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientDiagnosticController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $version = $client->diagnostics()->max('version') + 1;

        $diagnostic = ClientDiagnostic::create([
            'client_id'  => $client->id,
            'version'    => $version,
            'title'      => "Diagnóstico {$client->company_name} — v{$version}",
            'status'     => 'draft',
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('clients.diagnostics.edit', [$client, $diagnostic])
            ->with('success', 'Diagnóstico criado. Preencha as seções.');
    }

    public function edit(Client $client, ClientDiagnostic $diagnostic)
    {
        abort_unless($diagnostic->client_id === $client->id, 403);

        $diagnostic->load(['competitors', 'personas']);

        $currentSection = request('sec', 'sec01');
        if (!array_key_exists($currentSection, ClientDiagnostic::$sections)) {
            $currentSection = 'sec01';
        }

        return view('diagnostics.edit', compact('client', 'diagnostic', 'currentSection'));
    }

    public function update(Request $request, Client $client, ClientDiagnostic $diagnostic)
    {
        abort_unless($diagnostic->client_id === $client->id, 403);

        $section = $request->input('_section');
        $validSections = ['sec01', 'sec02', 'sec03', 'sec04', 'sec05', 'sec06'];

        if (!in_array($section, $validSections)) {
            return back()->with('error', 'Seção inválida.');
        }

        $columns = [
            'sec01' => 'sec01_briefing',
            'sec02' => 'sec02_marketing',
            'sec03' => 'sec03_audit',
            'sec04' => 'sec04_competition',
            'sec05' => 'sec05_persona',
            'sec06' => 'sec06_synthesis',
        ];

        $column = $columns[$section];
        $data   = $request->input($section, []);

        // Normalize checkboxes: absent = false
        if (isset($data['checklist']) && is_array($data['checklist'])) {
            // already sent as key=>value, normalize booleans
            $data['checklist'] = array_map(fn($v) => (bool) $v, $data['checklist']);
        }

        $diagnostic->update([$column => $data]);

        return redirect()->route('clients.diagnostics.edit', [$client, $diagnostic, 'sec' => $section])
            ->with('success', 'Seção salva com sucesso.');
    }

    public function complete(Request $request, Client $client, ClientDiagnostic $diagnostic)
    {
        abort_unless($diagnostic->client_id === $client->id, 403);

        $diagnostic->update([
            'status'       => 'complete',
            'completed_at' => now(),
        ]);

        return redirect()->route('clients.diagnostics.edit', [$client, $diagnostic])
            ->with('success', 'Diagnóstico marcado como concluído.');
    }

    public function reopen(Client $client, ClientDiagnostic $diagnostic)
    {
        abort_unless($diagnostic->client_id === $client->id, 403);

        $diagnostic->update(['status' => 'draft', 'completed_at' => null]);

        return redirect()->route('clients.diagnostics.edit', [$client, $diagnostic])
            ->with('success', 'Diagnóstico reaberto para edição.');
    }

    public function destroy(Client $client, ClientDiagnostic $diagnostic)
    {
        abort_unless($diagnostic->client_id === $client->id, 403);

        $diagnostic->delete();

        return redirect()->route('clients.show', [$client, 'tab' => 'diagnosticos'])
            ->with('success', 'Diagnóstico removido.');
    }
}

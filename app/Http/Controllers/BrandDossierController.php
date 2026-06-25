<?php

namespace App\Http\Controllers;

use App\Models\BrandDossier;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;

class BrandDossierController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $lastVersion = $client->dossiers()->max('version') ?? 0;

        $dossier = $client->dossiers()->create([
            'version'         => $lastVersion + 1,
            'title'           => 'Dossiê de Marca v' . ($lastVersion + 1),
            'fase'            => 'pesquisa_previa',
            'estrategista_id' => auth()->id(),
            'created_by'      => auth()->id(),
        ]);

        return redirect()->route('clients.dossiers.show', [$client, $dossier])
            ->with('success', 'Dossiê criado.');
    }

    public function show(Client $client, BrandDossier $dossier)
    {
        $dossier->load(['competitors', 'personas', 'estrategista', 'redator']);

        $users = User::whereHas('organizations', function ($q) {
            $q->where('organizations.id', app('currentOrganization')->id);
        })->orderBy('name')->get(['id', 'name']);

        return view('dossiers.show', compact('client', 'dossier', 'users'));
    }

    public function update(Request $request, Client $client, BrandDossier $dossier)
    {
        $data = $request->except(['_token', '_method']);

        // Normaliza campos vazios que são FK
        foreach (['estrategista_id', 'redator_id', 'data_kickoff'] as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        // Campos JSONB que chegam como string JSON do front
        $jsonFields = [
            'roteiro_pesquisa_previa', 'roteiro_kickoff_negocio', 'roteiro_kickoff_mercado',
            'roteiro_kickoff_cliente', 'roteiro_kickoff_comunicacao',
            'bloco02_objetivos', 'bloco03_valores', 'bloco03_posicionamento_formula',
            'bloco03_arquetipo', 'bloco04_exemplos_tom', 'bloco04_territorio_visual',
        ];

        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $decoded = json_decode($data[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data[$field] = $decoded;
                }
            }
        }

        try {
            $dossier->update($data);
        } catch (\Throwable $e) {
            \Log::error('dossier.update failed', [
                'dossier' => $dossier->id,
                'error'   => $e->getMessage(),
                'fields'  => array_keys($data),
            ]);
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            throw $e;
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'saved_at' => now()->format('H:i:s')]);
        }

        return back()->with('success', 'Dossiê salvo.');
    }

    public function avancaFase(Client $client, BrandDossier $dossier)
    {
        $proxima = $dossier->proximaFase();

        if (!$proxima) {
            return back()->with('error', 'Dossiê já está na fase final.');
        }

        $extra = [];
        if ($proxima === 'aprovado') {
            $extra['approved_at'] = now();
            $extra['approved_by'] = auth()->id();
        }

        $dossier->update(array_merge(['fase' => $proxima], $extra));

        return back()->with('success', 'Fase avançada para: ' . BrandDossier::$fases[$proxima]['label']);
    }

    public function destroy(Client $client, BrandDossier $dossier)
    {
        $dossier->delete();

        return redirect()->route('clients.show', $client)
            ->with('success', 'Dossiê removido.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\MacroPlanAttachment;
use App\Models\User;
use App\Services\MacroPlanHtmlImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MacroPlanImportController extends Controller
{
    public function create()
    {
        $clients = Client::orderBy('company_name')->get(['id', 'company_name']);

        return view('macroplans.import', compact('clients'));
    }

    public function store(Request $request, MacroPlanHtmlImporter $importer)
    {
        $data = $request->validate([
            'client_id'    => 'required|uuid|exists:pgsql.clients,id',
            'file'         => 'required|file|mimes:html,htm|max:5120',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after:period_start',
        ]);

        $client = Client::findOrFail($data['client_id']);

        $html = file_get_contents($data['file']->getRealPath());
        $parsed = $importer->parse($html);

        // O nome na Capa do HTML nem sempre bate com o cadastro (abreviação,
        // erro de digitação, etc.) — por isso quem manda é o cliente
        // selecionado explicitamente no form, não mais um match por nome.
        // Se o texto da Capa existir e não bater nem por substring, só avisa
        // (não bloqueia a importação).
        if (filled($parsed['client_name']) && !str_contains(
            \Illuminate\Support\Str::lower($client->company_name),
            \Illuminate\Support\Str::lower($parsed['client_name'])
        )) {
            $parsed['warnings'][] = "A Capa do HTML menciona \"{$parsed['client_name']}\", diferente do cliente selecionado (\"{$client->company_name}\") — confira se é o cliente certo.";
        }

        // Capa costuma trazer só o primeiro nome (ex: "Evandro") — busca por
        // substring, não match exato. Só usa se achar exatamente 1 pessoa.
        $responsibleId = null;
        if (filled($parsed['responsible_name'])) {
            $userMatches = User::where('name', 'ilike', '%' . $parsed['responsible_name'] . '%')->get();
            if ($userMatches->count() === 1) {
                $responsibleId = $userMatches->first()->id;
            }
        }

        $plan = DB::connection('pgsql')->transaction(function () use ($client, $parsed, $data, $request, $responsibleId) {
            $plan = $client->macroplans()->create([
                'title'          => $parsed['title'],
                'version'        => $parsed['version'] ?: null,
                'responsible_id' => $responsibleId,
                'period_start'   => $data['period_start'],
                'period_end'     => $data['period_end'],
                'status'         => 'em_planejamento',
                'disciplines'    => $parsed['disciplines'],
                'bloco1'         => $parsed['bloco1'],
                'bloco2'         => $parsed['bloco2'],
                'bloco4'         => $parsed['bloco4'],
                'bloco5'         => $parsed['bloco5'],
                'created_by'     => Auth::id(),
            ]);

            foreach ($parsed['projects'] as $i => $projectData) {
                $projectData['client_id'] = $client->id;
                $projectData['position']  = $i + 1;
                $projectData['created_by'] = Auth::id();
                $plan->projects()->create($projectData);
            }

            $file = $request->file('file');
            $disk = config('filesystems.default', 'r2');
            $path = $file->store("macroplans/{$plan->id}", $disk);
            MacroPlanAttachment::create([
                'macro_plan_id' => $plan->id,
                'filename'      => $file->getClientOriginalName(),
                'disk_path'     => $path,
                'disk'          => $disk,
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => Auth::id(),
            ]);

            return $plan;
        });

        $projectCount = count($parsed['projects']);
        $success = "Macroplanejamento importado: {$projectCount} projeto(s)/campanha(s) criado(s).";

        return redirect()->route('macroplans.edit', $plan)
            ->with('success', $success)
            ->with('import_warnings', $parsed['warnings']);
    }
}

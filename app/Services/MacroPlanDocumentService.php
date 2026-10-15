<?php

namespace App\Services;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\MacroPlanAttachment;
use App\Models\User;
use App\Support\UploadOptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Documento do planejamento (2026-10): o HTML gerado pela skill é o ativo principal — é ele
 * que a página exibe e o assistente lê. Fica num campo PRÓPRIO (macro_plans.document_attachment_id),
 * separado dos anexos diversos: subir um PDF/HTML qualquer em "Anexos" nunca troca o documento.
 *
 * Criar a partir do HTML (antes o botão "Importar HTML", agora dentro do "+ Novo Planejamento")
 * também lê o bloco de dados da skill e já cria as campanhas/projetos.
 */
class MacroPlanDocumentService
{
    public function __construct(private MacroPlanHtmlImporter $importer) {}

    /**
     * Lê o HTML — lança \InvalidArgumentException com mensagem pro usuário se não for
     * um planejamento reconhecível.
     */
    public function parse(UploadedFile $file): array
    {
        return $this->importer->parse(file_get_contents($file->getRealPath()));
    }

    /**
     * Cria o planejamento com o que veio do formulário ($attributes: client_id, period_*,
     * responsible_id, status, focus) + o que o HTML trouxe (verba, blocos antigos, campanhas
     * e projetos). Devolve [plano, avisos do import].
     */
    public function createFromDocument(Client $client, array $attributes, array $parsed, UploadedFile $file): array
    {
        $warnings = $parsed['warnings'] ?? [];

        // O nome na Capa nem sempre bate com o cadastro (abreviação, erro de digitação) —
        // quem manda é o cliente escolhido no formulário; aqui só avisa.
        if (filled($parsed['client_name'] ?? null) && !str_contains(
            Str::lower($client->company_name),
            Str::lower($parsed['client_name'])
        )) {
            $warnings[] = "O documento menciona \"{$parsed['client_name']}\", diferente do cliente selecionado (\"{$client->company_name}\") — confira se é o cliente certo.";
        }

        // Sem responsável escolhido, tenta o da Capa (costuma vir só o primeiro nome —
        // substring, e só se achar exatamente 1 pessoa).
        if (empty($attributes['responsible_id']) && filled($parsed['responsible_name'] ?? null)) {
            $matches = User::where('name', 'ilike', '%' . $parsed['responsible_name'] . '%')->get(['id']);
            if ($matches->count() === 1) {
                $attributes['responsible_id'] = $matches->first()->id;
            }
        }

        $plan = DB::connection('pgsql')->transaction(function () use ($client, $attributes, $parsed, $file) {
            $plan = $client->macroplans()->create([
                ...$attributes,
                // Coluna tem default '1.0' mas não é nullable — null explícito quebra o insert.
                'version'     => Str::limit($parsed['version'] ?: '1.0', 150, ''),
                'disciplines' => $parsed['disciplines'] ?? [],
                'bloco1'      => $parsed['bloco1'] ?? null,
                'bloco2'      => $parsed['bloco2'] ?? null,
                'bloco4'      => $parsed['bloco4'] ?? null,
                'bloco5'      => $parsed['bloco5'] ?? null,
                'created_by'  => Auth::id(),
            ]);

            foreach ($parsed['projects'] ?? [] as $i => $projectData) {
                $projectData['client_id']  = $client->id;
                $projectData['position']   = $i + 1;
                $projectData['created_by'] = Auth::id();
                $plan->projects()->create($projectData);
            }

            $this->setDocument($plan, $file);

            return $plan;
        });

        return [$plan, $warnings];
    }

    /** Sobe o HTML e marca como o documento do planejamento (o anterior fica nos anexos). */
    public function setDocument(MacroPlan $plan, UploadedFile $file): MacroPlanAttachment
    {
        $disk     = config('filesystems.default', 'r2');
        $mimeType = $file->getMimeType();
        $path     = $file->store("macroplans/{$plan->id}", UploadOptions::forStore($mimeType, $disk));

        $attachment = MacroPlanAttachment::create([
            'macro_plan_id' => $plan->id,
            'filename'      => $file->getClientOriginalName(),
            'disk_path'     => $path,
            'disk'          => $disk,
            'mime_type'     => $mimeType,
            'size'          => $file->getSize(),
            'uploaded_by'   => Auth::id(),
        ]);

        $plan->update(['document_attachment_id' => $attachment->id]);

        return $attachment;
    }
}

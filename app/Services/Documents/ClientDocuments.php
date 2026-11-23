<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\MacroPlan;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Documentos em campos vistos pelo CLIENTE (24/11): Portal e link do aviso de "em execução".
 * Regra única de quando o cliente pode ler: planejamento em execução (ou concluído); campanha/
 * projeto quando ele mesmo ou o planejamento dele está em execução (ou concluído). Rascunho
 * (Em Planejamento, Aprovação) nunca aparece para o cliente.
 */
class ClientDocuments
{
    public const LIVE = ['em_execucao', 'concluido'];

    public static function forPlan(MacroPlan $plan): ?Document
    {
        return in_array($plan->status, self::LIVE, true) ? self::docFor($plan) : null;
    }

    public static function forProject(Project $project): ?Document
    {
        $live = in_array($project->status, self::LIVE, true)
            || ($project->macro_plan_id && in_array(MacroPlan::withoutGlobalScopes()->whereKey($project->macro_plan_id)->value('status'), self::LIVE, true));

        return $live && $project->status !== 'cancelado' ? self::docFor($project) : null;
    }

    /** Campanhas e projetos do plano com documento que o cliente pode ler, na ordem do plano. */
    public static function planItems(MacroPlan $plan): Collection
    {
        if (!in_array($plan->status, self::LIVE, true)) {
            return collect();
        }

        return Project::withoutGlobalScopes()->where('macro_plan_id', $plan->id)
            ->whereNotIn('status', ['cancelado'])
            ->orderBy('position')->orderBy('created_at')
            ->get()
            ->filter(fn (Project $p) => self::docFor($p) !== null)
            ->values();
    }

    /** Sem escopo de organização: o link do aviso abre sem login (não há organização atual). */
    public static function docFor(MacroPlan|Project $owner): ?Document
    {
        return Document::withoutGlobalScopes()
            ->where('documentable_type', $owner->getMorphClass())
            ->where('documentable_id', $owner->id)
            ->first();
    }

    /** A versão do cliente (o mesmo visual do "Como o cliente vê"). */
    public static function view(Document $document): View
    {
        $schema = $document->schema();

        return view($schema->clientView(), [
            'document' => $document,
            'content'  => $document->content,
            'schema'   => $schema,
            'owner'    => $document->documentable,
        ]);
    }
}

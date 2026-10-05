<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\MacroPlanActivity;
use App\Models\Project;
use App\Models\ProjectActivity;

// Histórico do Projeto (ProjectActivity) + entrada/saída de projeto no
// histórico do Planejamento (MacroPlanActivity). Mesma régua do TaskObserver:
// só AÇÃO, nunca conteúdo — briefing vira "Briefing editado", sem o texto.
class ProjectObserver
{
    private const DATE_FIELDS = [
        'start_date'      => 'start_date_changed',
        'pieces_due_date' => 'pieces_due_date_changed',
        'end_date'        => 'end_date_changed',
    ];

    // Campos de texto do briefing (por Head + brief criativo de campanha) —
    // mudou qualquer um, loga uma linha só dizendo QUAIS mudaram.
    private const BRIEFING_FIELDS = [
        'objective'               => 'Objetivo',
        'briefing_criacao'        => 'Criação',
        'briefing_web'            => 'Web',
        'briefing_trafego'        => 'Tráfego',
        'briefing_setup'          => 'Setup',
        'briefing_social'         => 'Social',
        'briefing_seo'            => 'SEO',
        'briefing_email'          => 'E-mail',
        'briefing_estrategia'     => 'Estratégia',
        'briefing_relacionamento' => 'Relacionamento',
        'big_idea_titulo'         => 'Big Idea',
        'big_idea_manifesto'      => 'Manifesto',
        'territorio_alternativo'  => 'Território alternativo',
        'racional_estrategico'    => 'Racional',
        'frase_voz'               => 'Frase/voz',
        'assinatura'              => 'Assinatura',
        'ponto_atencao'           => 'Ponto de atenção',
    ];

    public function created(Project $project): void
    {
        ProjectActivity::log($project, 'created', null, null);

        if ($project->macro_plan_id) {
            MacroPlanActivity::log($project->macro_plan_id, 'project_added', null, $project->title);
        }
    }

    public function updated(Project $project): void
    {
        if ($project->wasChanged('title')) {
            ProjectActivity::log($project, 'title_changed', $project->getOriginal('title'), $project->title);
        }
        if ($project->wasChanged('status')) {
            ProjectActivity::log($project, 'status_changed',
                Project::$statuses[$project->getOriginal('status')]['label'] ?? $project->getOriginal('status'),
                $project->statusLabel());
        }
        if ($project->wasChanged('type')) {
            ProjectActivity::log($project, 'type_changed',
                Project::$types[$project->getOriginal('type')]['label'] ?? $project->getOriginal('type'),
                $project->typeLabel());
        }
        if ($project->wasChanged('brief_status')) {
            ProjectActivity::log($project, 'brief_status_changed',
                Project::$briefStatuses[$project->getOriginal('brief_status')]['label'] ?? $project->getOriginal('brief_status'),
                $project->briefStatusLabel());
        }

        if ($project->wasChanged('macro_plan_id')) {
            $old = $project->getOriginal('macro_plan_id');
            $new = $project->macro_plan_id;
            ProjectActivity::log($project, 'macro_plan_changed',
                $old ? MacroPlan::find($old)?->title : null,
                $new ? MacroPlan::find($new)?->title : 'Sem planejamento');

            if ($old && MacroPlan::whereKey($old)->exists()) {
                MacroPlanActivity::log($old, 'project_removed', null, $project->title);
            }
            if ($new) {
                MacroPlanActivity::log($new, 'project_added', null, $project->title);
            }
        }

        if ($project->wasChanged('client_id')) {
            ProjectActivity::log($project, 'client_changed',
                Client::find($project->getOriginal('client_id'))?->displayName(),
                Client::find($project->client_id)?->displayName());
        }

        foreach (self::DATE_FIELDS as $field => $action) {
            if ($project->wasChanged($field)) {
                ProjectActivity::log($project, $action,
                    $project->getOriginal($field)?->format('d/m/Y') ?? '—',
                    $project->$field?->format('d/m/Y') ?? '—');
            }
        }

        if ($project->wasChanged('budget')) {
            $fmt = fn ($v) => $v === null ? '—' : 'R$ ' . number_format((float) $v, 2, ',', '.');
            ProjectActivity::log($project, 'budget_changed', $fmt($project->getOriginal('budget')), $fmt($project->budget));
        }

        if ($project->wasChanged('disciplines')) {
            $labels = fn ($list) => collect($list ?? [])->map(fn ($d) => Project::$disciplines[$d] ?? $d)->implode(', ') ?: '—';
            ProjectActivity::log($project, 'disciplines_changed', $labels($project->getOriginal('disciplines')), $labels($project->disciplines));
        }

        $briefing = collect(self::BRIEFING_FIELDS)->filter(fn ($label, $field) => $project->wasChanged($field));
        if ($briefing->isNotEmpty()) {
            ProjectActivity::log($project, 'briefing_updated', null, $briefing->implode(', '));
        }
    }

    public function deleted(Project $project): void
    {
        // Apagar o Planejamento leva os projetos junto via cascade do banco (sem
        // evento), então aqui só cai remoção avulsa de projeto — confere mesmo
        // assim se o planejamento ainda existe antes de gravar.
        if ($project->macro_plan_id && MacroPlan::whereKey($project->macro_plan_id)->exists()) {
            MacroPlanActivity::log($project->macro_plan_id, 'project_removed', null, $project->title);
        }
    }
}

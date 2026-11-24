<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Pendency;
use App\Models\Project;
use App\Models\ProjectCheckin;
use App\Services\PendencyService;
use App\Services\Projects\CheckinSystem;
use Illuminate\Console\Command;

/**
 * Acompanhamento (etapa E.3, 24/11): no dia da conferência, abre para o coordenador a
 * pendência "Conferir a campanha — X" (ou "Fechar ..." quando é a conferência de fechamento).
 * Ela se resolve sozinha quando a conferência é registrada (ProjectCheckinController@store).
 *
 * Só campanha/projeto Em Execução, com coordenador e documento em campos. Uma pendência aberta
 * por vez por campanha/projeto; depois do fechamento, não abre mais. Agendado todo dia
 * (routes/console.php).
 */
class CreateProjectCheckinPendencies extends Command
{
    protected $signature = 'projects:checkin-pendencies {--dry : Só lista o que criaria}';

    protected $description = 'Abre a pendência de conferência para o coordenador das campanhas e projetos no dia combinado';

    public function handle(PendencyService $pendencies): int
    {
        $morph = (new Project)->getMorphClass();
        $docs  = Document::withoutGlobalScopes()->whereIn('kind', ['campanha', 'projeto'])
            ->where('documentable_type', $morph)->get(['id', 'kind', 'content', 'documentable_id'])->keyBy('documentable_id');

        $projects = Project::withoutGlobalScopes()
            ->where('status', 'em_execucao')
            ->whereNotNull('coordinator_id')
            ->whereIn('id', $docs->keys())
            ->whereNotExists(fn ($q) => $q->from('pendencies')
                ->whereIn('pendable_type', ['project', $morph])
                ->whereColumn('pendable_id', 'projects.id')
                ->whereIn('status', Pendency::OPEN_STATUSES)
                ->where(fn ($w) => collect(ProjectCheckin::PENDENCY_PREFIXES)->each(fn ($p) => $w->orWhere('title', 'like', $p . ' %'))))
            ->get();

        $created = 0;
        foreach ($projects as $project) {
            $doc    = $docs[$project->id];
            $last   = ProjectCheckin::withoutGlobalScopes()->where('project_id', $project->id)->orderByDesc('checked_on')->orderByDesc('created_at')->first();
            $closed = ProjectCheckin::withoutGlobalScopes()->where('project_id', $project->id)->where('is_closing', true)->exists();
            $sched  = CheckinSystem::schedule($doc->kind, $doc->content ?? [], $last, $closed);
            if (!$sched['next'] || $sched['next']->gt(today())) {
                continue;
            }

            $title = ProjectCheckin::pendencyTitle($project, $sched['closing_due']);
            $this->line("  + {$title} (coordenador {$project->coordinator_id}, vence {$sched['next']->format('d/m')})");
            $created++;
            if ($this->option('dry')) {
                continue;
            }

            $coord = (int) $project->coordinator_id;
            $pendencies->create([
                'organization_id' => $project->organization_id,
                'title'           => $title,
                'description'     => $sched['closing_due']
                    ? 'Chegou a data do fechamento. Na aba Acompanhamento, registre a última conferência marcando "Esta é a conferência de fechamento" e preencha o resultado. A pendência se resolve sozinha.'
                    : 'Chegou o dia de conferir. Na aba Acompanhamento, avalie as metas, os pontos de cuidado e o andamento, e escreva o próximo passo. A pendência se resolve sozinha quando a conferência for registrada.',
                'kind'            => 'outro',
                'owed_by'         => 'agencia',
                'owed_user_id'    => $coord,
                'owner_id'        => $coord,
                'requested_by'    => $coord,
                'due_date'        => $sched['next']->toDateString(),
            ], $project, $coord, notifyActor: true);
        }

        $this->info($created . ' pendência(s) ' . ($this->option('dry') ? 'a criar.' : 'criada(s).'));

        return self::SUCCESS;
    }
}

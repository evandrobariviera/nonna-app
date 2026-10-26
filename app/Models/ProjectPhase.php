<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Fase ou marco da linha do tempo de uma campanha/projeto (2026-10-26).
 *
 * - Fase: trecho com começo e fim ("Produção das peças, 03/11 a 14/11"). As tarefas do
 *   projeto pertencem a uma fase (tasks.project_phase_id).
 * - Marco: um dia que não pode escorregar ("Apresentação ao cliente") — só end_date, sem
 *   tarefas. Os marcos fixos do projeto (Início/Vai ao ar, Entrega das peças, Término) não
 *   são gravados aqui: saem das datas do próprio projeto (ProjectTimeline).
 *
 * Status nunca é gravado: sai das tarefas (combinado com o Evandro — fase se conclui sozinha
 * quando a última tarefa termina; se sobrar tarefa, o time encerra a tarefa).
 */
class ProjectPhase extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'project_id',
        'position',
        'name',
        'is_milestone',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'position'     => 'integer',
        'is_milestone' => 'boolean',
        'start_date'   => 'date',
        'end_date'     => 'date',
    ];

    public static array $statuses = [
        'a_comecar'    => ['label' => 'A começar',    'color' => 'muted'],
        'em_andamento' => ['label' => 'Em andamento', 'color' => 'purple'],
        'atrasada'     => ['label' => 'Atrasada',     'color' => 'red'],
        'concluida'    => ['label' => 'Concluída',    'color' => 'green'],
        // marcos
        'proximo'      => ['label' => 'Próximo',      'color' => 'orange'],
        'passou'       => ['label' => 'Passou',       'color' => 'muted'],
    ];

    /**
     * Modelos pra começar a linha do tempo sem partir do zero (só nomes — as datas a pessoa
     * preenche). Etapa 2 leva isso pros Playbooks, com prazos calculados.
     * "Vai ao ar"/"Início"/"Término" não entram: já são marcos fixos das datas do projeto.
     */
    public static array $presets = [
        'campanha' => [
            'label'  => 'Campanha',
            'phases' => [
                ['name' => 'Conceito e roteiro'],
                ['name' => 'Produção das peças'],
                ['name' => 'Aprovação do cliente'],
                ['name' => 'Veiculação'],
                ['name' => 'Relatório e aprendizados'],
            ],
        ],
        'site' => [
            'label'  => 'Site',
            'phases' => [
                ['name' => 'Briefing e conteúdo'],
                ['name' => 'Wireframe'],
                ['name' => 'Design'],
                ['name' => 'Desenvolvimento'],
                ['name' => 'Testes e ajustes'],
                ['name' => 'Publicação', 'is_milestone' => true],
            ],
        ],
        'onboarding' => [
            'label'  => 'Onboarding',
            'phases' => [
                ['name' => 'Boas-vindas e automações (Dia 0)'],
                ['name' => 'Welcome call e acessos (Dias 1-2)'],
                ['name' => 'Setup técnico (Dias 3-4)'],
                ['name' => 'Kick-off estratégico (Dia 5)'],
                ['name' => 'Macroplanejamento e sprint'],
            ],
        ],
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_phase_id');
    }

    /**
     * Status a partir das tarefas da fase — recebe a coleção já carregada (a página do
     * projeto já tem todas as tarefas em memória), então não faz consulta.
     *
     * Concluída = tem tarefa e nenhuma aberta. Fase sem tarefa nunca se conclui sozinha:
     * passou do fim sem tarefa = atrasada (sinal de que faltou lançar ou a fase sobrou).
     */
    public function statusFrom(Collection $tasks): string
    {
        $today = now()->startOfDay();

        if ($this->is_milestone) {
            return $this->end_date && $this->end_date->lt($today) ? 'passou' : 'proximo';
        }

        $active = $tasks->where('status', '!=', 'cancelado');
        $open   = $active->where('status', '!=', 'concluido');

        if ($active->isNotEmpty() && $open->isEmpty()) {
            return 'concluida';
        }
        if ($this->end_date && $this->end_date->lt($today)) {
            return 'atrasada';
        }
        if ($active->contains(fn ($t) => $t->status !== 'backlog') || ($this->start_date && $this->start_date->lte($today))) {
            return 'em_andamento';
        }

        return 'a_comecar';
    }

    public static function statusLabel(string $status): string
    {
        return self::$statuses[$status]['label'] ?? $status;
    }

    public static function statusColor(string $status): string
    {
        return self::$statuses[$status]['color'] ?? 'muted';
    }
}

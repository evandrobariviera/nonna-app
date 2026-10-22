<?php

namespace App\Services;

use App\Models\MacroPlan;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pauta citada da reunião (2026-10-22): planejamentos, campanhas/projetos e tarefas que a
 * reunião discute, além do texto livre da pauta. A reunião aparece sozinha nas páginas do
 * que citou (e acima: tarefa → projeto → planejamento — ver Meeting::scopeRelatedTo*).
 */
class MeetingAgendaService
{
    /** Alias do morphMap → model. */
    public const TYPES = [
        'macro_plan' => MacroPlan::class,
        'project'    => Project::class,
        'task'       => Task::class,
    ];

    /**
     * Grava a pauta citada na ordem recebida. Item que não existe (ou é de outra
     * organização — Tenantable) é ignorado. Reunião sem cliente herda o do primeiro item.
     *
     * @param array<int, array{type:string, id:string}> $items
     */
    public function sync(Meeting $meeting, array $items): void
    {
        $found = $this->resolve($items);

        DB::connection('pgsql')->transaction(function () use ($meeting, $found) {
            foreach (self::TYPES as $alias => $class) {
                $relation = match ($alias) {
                    'macro_plan' => $meeting->agendaMacroPlans(),
                    'project'    => $meeting->agendaProjects(),
                    'task'       => $meeting->agendaTasks(),
                };
                $relation->sync($found->where('type', $alias)
                    ->mapWithKeys(fn ($item) => [$item['model']->getKey() => ['position' => $item['position']]])
                    ->all());
            }

            if (!$meeting->client_id && ($clientId = $found->map(fn ($i) => $this->clientIdOf($i['model']))->filter()->first())) {
                $meeting->update(['client_id' => $clientId]);
            }
        });
    }

    /** O que já está citado, no formato do seletor da tela (create/edit). */
    public function formItems(Meeting $meeting): array
    {
        $meeting->loadMissing(['agendaMacroPlans', 'agendaProjects.client', 'agendaTasks.client']);

        return collect()
            ->merge($meeting->agendaMacroPlans->map(fn ($m) => $this->present('macro_plan', $m) + ['position' => $m->pivot->position]))
            ->merge($meeting->agendaProjects->map(fn ($p) => $this->present('project', $p) + ['position' => $p->pivot->position]))
            ->merge($meeting->agendaTasks->map(fn ($t) => $this->present('task', $t) + ['position' => $t->pivot->position]))
            ->sortBy('position')->values()
            ->map(fn ($i) => collect($i)->except('position')->all())
            ->all();
    }

    /**
     * Busca do seletor: até 5 de cada tipo. Com cliente escolhido, só dele; sem termo,
     * os mais recentes.
     */
    public function search(string $term, ?string $clientId): array
    {
        $like = '%' . addcslashes(trim($term), '%_\\') . '%';
        $base = fn ($query, string $column) => $query
            ->when(trim($term) !== '', fn ($q) => $q->where($column, 'ilike', $like))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->orderByDesc('updated_at')->limit(5);

        $plans = $base(MacroPlan::with('client:id,company_name,nickname'), 'title')
            ->get(['id', 'title', 'client_id', 'period_start', 'period_end', 'status', 'updated_at']);
        $projects = $base(Project::with('client:id,company_name,nickname')->whereNotIn('status', ['cancelado']), 'title')
            ->get(['id', 'title', 'type', 'status', 'client_id', 'macro_plan_id', 'updated_at']);
        $tasks = $base(Task::with('client:id,company_name,nickname')->where('status', '!=', 'cancelado'), 'title')
            ->get(['id', 'title', 'status', 'client_id', 'is_ticket', 'updated_at']);

        return collect()
            ->merge($plans->map(fn ($m) => $this->present('macro_plan', $m)))
            ->merge($projects->map(fn ($p) => $this->present('project', $p)))
            ->merge($tasks->map(fn ($t) => $this->present('task', $t)))
            ->values()->all();
    }

    private function present(string $type, $model): array
    {
        $client = $model->client?->displayName();

        return match ($type) {
            'macro_plan' => ['type' => $type, 'id' => $model->id, 'kind' => 'Planejamento', 'label' => $model->title,
                'sub' => $model->statusLabel()],
            'project'    => ['type' => $type, 'id' => $model->id, 'kind' => $model->typeLabel(), 'label' => $model->title,
                'sub' => trim(($client ? $client . ' · ' : '') . $model->statusLabel())],
            'task'       => ['type' => $type, 'id' => $model->id, 'kind' => $model->is_ticket ? 'Chamado' : 'Tarefa', 'label' => $model->title,
                'sub' => trim(($client ? $client . ' · ' : '') . $model->statusLabel())],
        };
    }

    /** @return Collection<int, array{type:string, model:mixed, position:int}> */
    private function resolve(array $items): Collection
    {
        $items = collect($items)
            ->filter(fn ($i) => isset(self::TYPES[$i['type'] ?? '']) && !empty($i['id']))
            ->unique(fn ($i) => $i['type'] . ':' . $i['id'])
            ->values();

        $models = $items->groupBy('type')->map(fn ($group, $type) => (self::TYPES[$type])::whereIn('id', $group->pluck('id'))->get()->keyBy('id'));

        return $items->map(fn ($i, $position) => [
            'type'     => $i['type'],
            'model'    => $models[$i['type']][$i['id']] ?? null,
            'position' => $position,
        ])->filter(fn ($i) => $i['model'] !== null)->values();
    }

    private function clientIdOf($model): ?string
    {
        return $model instanceof Project ? $model->resolvedClientId() : $model->client_id;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskFormat;
use App\Models\TaskTypePoint;
use App\Services\Tasks\SprintPoints;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// Configurações → Pontos de Sprint: ponto padrão por tipo, catálogo de formatos e o botão
// de recalcular as tarefas existentes. Ver App\Services\Tasks\SprintPoints.
class SprintPointsSettingsController extends Controller
{
    public function updateTypes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'points'   => ['required', 'array'],
            'points.*' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        foreach (array_intersect_key($data['points'], Task::$types) as $type => $points) {
            if ($points === null) {
                continue;
            }
            TaskTypePoint::updateOrCreate(
                ['organization_id' => app('currentOrganization')->id, 'task_type' => $type],
                ['points' => $points]
            );
        }

        return back()->with('success', 'Pontos padrão por tipo salvos. Use "Recalcular" pra aplicar nas tarefas existentes.')->with('tab', 'pontos');
    }

    public function storeFormat(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['position'] = (int) TaskFormat::where('task_type', $data['task_type'])->max('position') + 1;
        TaskFormat::create($data + ['organization_id' => app('currentOrganization')->id]);

        return back()->with('success', 'Formato criado.')->with('tab', 'pontos');
    }

    public function updateFormat(Request $request, TaskFormat $format): RedirectResponse
    {
        $format->update($this->validated($request));

        return back()->with('success', 'Formato atualizado. Use "Recalcular" pra aplicar nas tarefas existentes.')->with('tab', 'pontos');
    }

    public function destroyFormat(TaskFormat $format): RedirectResponse
    {
        $format->delete(); // tarefas que tinham esse formato escolhido voltam pro automático (nullOnDelete)

        return back()->with('success', 'Formato removido.')->with('tab', 'pontos');
    }

    // Ordem importa (o primeiro formato que bate no título vence) — sobe/desce uma posição.
    public function moveFormat(TaskFormat $format, string $direction): RedirectResponse
    {
        $siblings = TaskFormat::where('task_type', $format->task_type)->orderBy('position')->get()->values();
        $i = $siblings->search(fn ($f) => $f->id === $format->id);
        $j = $direction === 'up' ? $i - 1 : $i + 1;

        if ($i !== false && isset($siblings[$j])) {
            $list = $siblings->all();
            [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
            foreach ($list as $pos => $f) {
                $f->update(['position' => $pos]);
            }
        }

        return back()->with('tab', 'pontos');
    }

    public function recalculate(SprintPoints $points): RedirectResponse
    {
        $changed = $points->recalculate(app('currentOrganization')->id);

        return back()->with('success', "Pontos recalculados: {$changed} tarefa(s) mudaram. Ajustes feitos à mão foram mantidos.")->with('tab', 'pontos');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'task_type' => ['required', 'in:' . implode(',', array_keys(Task::$types))],
            'name'      => ['required', 'string', 'max:120'],
            'points'    => ['required', 'integer', 'min:0', 'max:100'],
            'keywords'  => ['nullable', 'string', 'max:1000'],
        ]);
    }
}

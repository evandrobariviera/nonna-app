<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectPhase;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Linha do tempo da campanha/projeto (ProjectPhase) — fases e marcos editados de uma vez só
// (lista inteira), e a troca de fase de uma tarefa direto da linha do tempo ou da tarefa.
class ProjectPhaseController extends Controller
{
    public function sync(Request $request, Project $project)
    {
        $data = $request->validate([
            'phases'                => 'nullable|array|max:40',
            'phases.*.id'           => 'nullable|uuid',
            'phases.*.name'         => 'required|string|max:150',
            'phases.*.is_milestone' => 'nullable|boolean',
            'phases.*.start_date'   => 'nullable|date',
            'phases.*.end_date'     => 'nullable|date',
        ], [
            'phases.*.name.required' => 'Toda fase/marco precisa de nome.',
        ]);

        $rows = array_values($data['phases'] ?? []);
        foreach ($rows as $i => $row) {
            $milestone = !empty($row['is_milestone']);
            if ($milestone && empty($row['end_date'])) {
                throw ValidationException::withMessages(["phases.$i.end_date" => "O marco \"{$row['name']}\" precisa de data."]);
            }
            if (!$milestone && !empty($row['start_date']) && !empty($row['end_date']) && $row['end_date'] < $row['start_date']) {
                throw ValidationException::withMessages(["phases.$i.end_date" => "A fase \"{$row['name']}\" termina antes de começar."]);
            }
        }

        DB::connection('pgsql')->transaction(function () use ($project, $rows) {
            $existing = $project->phases()->get()->keyBy('id');
            $keep     = [];

            foreach ($rows as $i => $row) {
                $milestone = !empty($row['is_milestone']);
                $attrs = [
                    'position'     => $i + 1,
                    'name'         => trim($row['name']),
                    'is_milestone' => $milestone,
                    'start_date'   => $milestone ? null : ($row['start_date'] ?? null),
                    'end_date'     => $row['end_date'] ?? null,
                ];

                $phase = !empty($row['id']) ? $existing->get($row['id']) : null;
                if ($phase) {
                    $phase->update($attrs);
                    // Virou marco: marco não tem tarefa — as dela ficam sem fase.
                    if ($milestone) {
                        $phase->tasks()->update(['project_phase_id' => null]);
                    }
                } else {
                    $phase = $project->phases()->create($attrs + ['organization_id' => $project->organization_id]);
                }
                $keep[] = $phase->id;
            }

            // Removidas: as tarefas delas ficam sem fase (FK nullOnDelete).
            $project->phases()->whereNotIn('id', $keep)->delete();
        });

        $phases     = collect($rows)->reject(fn ($r) => !empty($r['is_milestone']))->count();
        $milestones = count($rows) - $phases;
        ProjectActivity::log($project, 'phases_updated', null, "{$phases} fase(s), {$milestones} marco(s)");

        return redirect()->back()->with('success', 'Linha do tempo atualizada.');
    }

    // Troca a fase de uma tarefa (linha do tempo do projeto e página da tarefa) — sempre AJAX.
    public function updateTask(Request $request, Task $task)
    {
        $data = $request->validate(['project_phase_id' => 'nullable|uuid']);

        if ($data['project_phase_id']) {
            $phase = ProjectPhase::find($data['project_phase_id']);
            if (!$phase || (string) $phase->project_id !== (string) $task->project_id || $phase->is_milestone) {
                return response()->json(['message' => 'Essa fase não é do projeto da tarefa.'], 422);
            }
        }

        $task->update(['project_phase_id' => $data['project_phase_id']]);

        return response()->json(['success' => true]);
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionController extends Controller
{
    // Visão única de tudo que está ativo pro cliente — junta Fila (sprint_id
    // null), Sprint (sprint_id preenchido) e Chamados (is_ticket true), já que
    // todos são apenas `tasks` por baixo. Organizada por data de entrega, não
    // por origem — o cliente não precisa saber se é backlog ou sprint pra
    // entender "o que vem quando".
    public function index(Request $request): View
    {
        $client = app('currentPortalClient');

        $query = Task::where('client_id', $client->id)
            ->with(['project', 'sprint'])
            ->orderByRaw('due_date IS NULL, due_date ASC');

        if (!$request->boolean('mostrar_concluidas')) {
            $query->whereNotIn('status', ['concluido', 'cancelado']);
        }

        $tasks = $query->get();

        $today = today();
        $endOfWeek = today()->endOfWeek();

        $groups = [
            'atrasadas'  => ['label' => 'Atrasadas',        'tasks' => collect()],
            'hoje'       => ['label' => 'Hoje',              'tasks' => collect()],
            'semana'     => ['label' => 'Esta Semana',       'tasks' => collect()],
            'depois'     => ['label' => 'Mais Adiante',      'tasks' => collect()],
            'sem_prazo'  => ['label' => 'Sem Prazo Definido','tasks' => collect()],
        ];

        foreach ($tasks as $task) {
            if (!$task->due_date) {
                $groups['sem_prazo']['tasks']->push($task);
            } elseif ($task->isOverdue()) {
                $groups['atrasadas']['tasks']->push($task);
            } elseif ($task->due_date->isSameDay($today)) {
                $groups['hoje']['tasks']->push($task);
            } elseif ($task->due_date->lte($endOfWeek)) {
                $groups['semana']['tasks']->push($task);
            } else {
                $groups['depois']['tasks']->push($task);
            }
        }

        $mostrarConcluidas = $request->boolean('mostrar_concluidas');

        $stats = [
            'total'     => $tasks->count(),
            'atrasadas' => $groups['atrasadas']['tasks']->count(),
        ];

        return view('portal.production.index', compact('client', 'groups', 'stats', 'mostrarConcluidas'));
    }
}

<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ResetOperacionalController extends Controller
{
    public function index()
    {
        $counts = [
            'tasks'              => DB::connection('pgsql')->table('tasks')->count(),
            'projects'           => DB::connection('pgsql')->table('projects')->count(),
            'macro_plans'        => DB::connection('pgsql')->table('macro_plans')->count(),
            'sprints'            => DB::connection('pgsql')->table('sprints')->count(),
            'opportunities'      => DB::connection('pgsql')->table('opportunities')->count(),
            'client_onboardings' => DB::connection('pgsql')->table('client_onboardings')->count(),
        ];

        return view('superadmin.reset-operacional', compact('counts'));
    }

    public function execute()
    {
        DB::connection('pgsql')->statement('
            TRUNCATE TABLE
                task_approval_tokens,
                task_approval_rounds,
                task_comments,
                task_attachments,
                task_executors,
                tasks,
                sprints,
                projects,
                macro_plans,
                opportunities,
                client_onboardings
            RESTART IDENTITY CASCADE
        ');

        return redirect()->route('superadmin.dashboard')
            ->with('success', 'Reset operacional concluído. Tarefas, projetos, planejamentos, sprints, oportunidades e onboardings removidos.');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // O cliente nasce com o primeiro ciclo (planejamento de 90 dias + Onboarding), então a
    // tarefa da Fase 5 não "cria" planejamento: envia o HTML do kick-off nesse ciclo, o que
    // acrescenta as campanhas/projetos (MacroPlanDocumentService::importIntoPlan).
    public function up(): void
    {
        $playbookIds = DB::connection('pgsql')->table('project_playbooks')
            ->where('name', 'Onboarding de Cliente')->pluck('id');

        DB::connection('pgsql')->table('project_playbook_tasks')
            ->whereIn('project_playbook_id', $playbookIds)
            ->where('title', 'Criar o macroplanejamento')
            ->update([
                'title'       => 'Enviar o planejamento do kick-off no planejamento do ciclo',
                'description' => 'O cliente já nasceu com o planejamento de 90 dias (onde está este Onboarding). Gere o HTML na skill de planejamento e envie em "Enviar documento" na página do planejamento: as campanhas e projetos entram nele.',
                'updated_at'  => now(),
            ]);
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('project_playbook_tasks')
            ->where('title', 'Enviar o planejamento do kick-off no planejamento do ciclo')
            ->update(['title' => 'Criar o macroplanejamento', 'description' => null]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // Pauta citada da reunião (2026-10-22): além do texto livre, a reunião cita
    // planejamentos, campanhas/projetos e tarefas — e aparece sozinha neles (citar uma
    // tarefa também mostra a reunião no projeto e no planejamento dela; ver
    // Meeting::scopeRelatedTo*). Substitui meeting_projects (só campanhas/projetos,
    // criada em 2026-10-21 pra reunião de discussão) — os vínculos são copiados.
    // item_type usa os aliases do morphMap: macro_plan | project | task.
    public function up(): void
    {
        Schema::connection('pgsql')->create('meeting_agenda_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->uuid('item_id');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['meeting_id', 'item_type', 'item_id']);
            $table->index(['item_type', 'item_id']);
        });

        if (Schema::connection('pgsql')->hasTable('meeting_projects')) {
            DB::connection('pgsql')->statement("
                INSERT INTO meeting_agenda_items (meeting_id, item_type, item_id, position, created_at, updated_at)
                SELECT meeting_id, 'project', project_id, position, created_at, updated_at FROM meeting_projects
            ");
            Schema::connection('pgsql')->drop('meeting_projects');
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->create('meeting_projects', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['meeting_id', 'project_id']);
            $table->index('project_id');
        });
        DB::connection('pgsql')->statement("
            INSERT INTO meeting_projects (meeting_id, project_id, position, created_at, updated_at)
            SELECT meeting_id, item_id, position, created_at, updated_at FROM meeting_agenda_items WHERE item_type = 'project'
        ");
        Schema::connection('pgsql')->dropIfExists('meeting_agenda_items');
    }
};

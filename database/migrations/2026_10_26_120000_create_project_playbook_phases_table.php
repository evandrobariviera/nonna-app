<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // Fases-modelo no Playbook (linha do tempo, etapa 2 — 2026-10-26): ao aplicar o Playbook,
    // as fases viram ProjectPhase com datas calculadas a partir do início do projeto, e cada
    // tarefa do modelo cai na sua fase.
    public function up(): void
    {
        Schema::connection('pgsql')->create('project_playbook_phases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_playbook_id')->constrained('project_playbooks')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('name', 150);
            $table->boolean('is_milestone')->default(false);
            $table->unsignedSmallInteger('start_offset_days')->nullable(); // dias após o início do projeto
            $table->unsignedSmallInteger('duration_days')->nullable();     // fase: quantos dias dura (marco ignora)
            $table->timestamps();

            $table->index(['project_playbook_id', 'position']);
        });

        Schema::connection('pgsql')->table('project_playbook_tasks', function (Blueprint $table) {
            $table->foreignUuid('project_playbook_phase_id')->nullable()->constrained('project_playbook_phases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('project_playbook_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_playbook_phase_id');
        });
        Schema::connection('pgsql')->dropIfExists('project_playbook_phases');
    }
};

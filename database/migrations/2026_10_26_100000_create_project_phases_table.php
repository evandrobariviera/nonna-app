<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // Linha do tempo de campanha/projeto (2026-10-26): fases (trecho com começo e fim —
    // "Produção das peças") e marcos (um dia só — "Apresentação ao cliente"). Toda tarefa do
    // projeto pertence a uma fase; a fase se conclui sozinha quando a última tarefa dela
    // termina (ProjectPhase::status) — não existe botão de concluir fase.
    public function up(): void
    {
        Schema::connection('pgsql')->create('project_phases', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('name', 150);
            $table->boolean('is_milestone')->default(false); // marco: só end_date (o dia do marco)
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'position']);
        });

        // Coluna nullable sem default: no Postgres é só metadado (instantâneo), não reescreve tasks.
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->foreignUuid('project_phase_id')->nullable()->constrained('project_phases')->nullOnDelete();
            $table->index('project_phase_id');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_phase_id');
        });
        Schema::connection('pgsql')->dropIfExists('project_phases');
    }
};

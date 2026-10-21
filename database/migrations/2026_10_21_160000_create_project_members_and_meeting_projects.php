<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // Fluxo de discussão de campanhas/projetos (2026-10-21): a equipe de cada campanha/projeto
    // (quem participa — muda de uma campanha pra outra) e a pauta da reunião de discussão
    // (uma reunião pode agrupar vários itens do mesmo planejamento, cada um com sua equipe).
    public function up(): void
    {
        Schema::connection('pgsql')->create('project_members', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        Schema::connection('pgsql')->create('meeting_projects', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['meeting_id', 'project_id']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('meeting_projects');
        Schema::connection('pgsql')->dropIfExists('project_members');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Página do Projeto no Portal (Central de Aprovações): texto curto que o
    // cliente lê no topo — separado de objective/briefings, que são internos
    // (mesmo princípio de tasks.caption vs tasks.description) — e a tarefa em
    // destaque (conceito da campanha, layout da home...), opcional.
    public function up(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->text('client_description')->nullable();
            $table->foreignUuid('highlight_task_id')->nullable()->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('highlight_task_id');
            $table->dropColumn('client_description');
        });
    }
};

<?php

use App\Services\Tasks\SprintPoints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Pontos de sprint (ver App\Services\Tasks\SprintPoints): catálogo de formatos por tipo de
    // tarefa (Post estático, Reels, Landing page...) com pontos e palavras-chave pra detectar o
    // formato pelo título; ponto padrão por tipo quando nenhum formato bate; e na tarefa o
    // formato escolhido (opcional), os pontos e se foram ajustados à mão.
    public function up(): void
    {
        Schema::connection('pgsql')->create('task_formats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('task_type', 40);
            $table->string('name');
            $table->unsignedSmallInteger('points');
            $table->text('keywords')->nullable(); // separadas por vírgula, sem acento
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'task_type', 'position']);
        });

        Schema::connection('pgsql')->create('task_type_points', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('task_type', 40);
            $table->unsignedSmallInteger('points');
            $table->timestamps();

            $table->unique(['organization_id', 'task_type']);
        });

        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->foreignUuid('task_format_id')->nullable()->constrained('task_formats')->nullOnDelete();
            $table->unsignedSmallInteger('sprint_points')->nullable();
            $table->boolean('sprint_points_manual')->default(false);
        });

        // Catálogo inicial (montado a partir dos títulos reais das tarefas) + pontos em toda
        // tarefa já existente — o usuário pediu que as antigas também recebam pontuação; quando
        // os valores forem fechados, o botão "Recalcular" em Configurações → Pontos de Sprint
        // reaplica o catálogo (preservando os ajustes manuais).
        foreach (DB::connection('pgsql')->table('organizations')->pluck('id') as $orgId) {
            SprintPoints::seedDefaults($orgId);
            app(SprintPoints::class)->recalculate($orgId);
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_format_id');
            $table->dropColumn(['sprint_points', 'sprint_points_manual']);
        });
        Schema::connection('pgsql')->dropIfExists('task_type_points');
        Schema::connection('pgsql')->dropIfExists('task_formats');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Histórico de AÇÕES de Projeto e de Planejamento — mesmo formato de
    // task_activities: só rótulos (de/para), quem e quando; nunca texto livre
    // (briefing/blocos aparecem só como "editado", sem o conteúdo).
    public function up(): void
    {
        Schema::connection('pgsql')->create('project_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('from_label')->nullable();
            $table->string('to_label')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
        });

        Schema::connection('pgsql')->create('macro_plan_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('macro_plan_id')->constrained('macro_plans')->cascadeOnDelete();
            $table->string('action');
            $table->string('from_label')->nullable();
            $table->string('to_label')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['macro_plan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('macro_plan_activities');
        Schema::connection('pgsql')->dropIfExists('project_activities');
    }
};

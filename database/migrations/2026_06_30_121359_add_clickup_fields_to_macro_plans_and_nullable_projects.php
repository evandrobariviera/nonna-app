<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        // clickup_task_id em macro_plans para idempotência na importação
        Schema::connection('pgsql')->table('macro_plans', function (Blueprint $table) {
            $table->string('clickup_task_id', 100)->nullable()->unique()->after('launched_at');
        });

        // macro_plan_id nullable em projects — projetos importados sem planejamento pai explícito
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->dropForeign(['macro_plan_id']);
            $table->uuid('macro_plan_id')->nullable()->change();
            $table->foreign('macro_plan_id')
                ->references('id')->on('macro_plans')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('macro_plans', function (Blueprint $table) {
            $table->dropUnique(['macro_plans_clickup_task_id_unique']);
            $table->dropColumn('clickup_task_id');
        });

        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->dropForeign(['macro_plan_id']);
            $table->uuid('macro_plan_id')->nullable(false)->change();
            $table->foreign('macro_plan_id')
                ->references('id')->on('macro_plans')
                ->onDelete('cascade');
        });
    }
};

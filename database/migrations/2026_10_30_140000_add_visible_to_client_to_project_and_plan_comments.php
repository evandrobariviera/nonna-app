<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Mesma regra de task_comments: nasce interno. Comentário antigo nunca vaza pro Portal.
    public function up(): void
    {
        foreach (['project_comments', 'macro_plan_comments'] as $table) {
            Schema::connection('pgsql')->table($table, function (Blueprint $t) {
                $t->boolean('visible_to_client')->default(false)->after('body');
            });
        }
    }

    public function down(): void
    {
        foreach (['project_comments', 'macro_plan_comments'] as $table) {
            Schema::connection('pgsql')->table($table, fn (Blueprint $t) => $t->dropColumn('visible_to_client'));
        }
    }
};

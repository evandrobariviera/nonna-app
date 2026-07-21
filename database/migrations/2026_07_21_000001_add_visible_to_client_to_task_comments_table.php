<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('task_comments', function (Blueprint $table) {
            // Comentários existentes ficam internos por padrão — sem isso, abrir a
            // thread pro portal vazaria retroativamente tudo que a equipe já escreveu.
            $table->boolean('visible_to_client')->default(false)->after('body');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_comments', function (Blueprint $table) {
            $table->dropColumn('visible_to_client');
        });
    }
};

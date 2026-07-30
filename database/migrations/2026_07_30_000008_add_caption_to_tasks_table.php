<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            // Legenda/texto que vai pro cliente aprovar junto com o material —
            // diferente de "description" (briefing interno, nunca sai do App).
            $table->text('caption')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->dropColumn('caption');
        });
    }
};

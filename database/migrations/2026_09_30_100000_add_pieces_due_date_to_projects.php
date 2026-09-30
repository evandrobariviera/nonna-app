<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            // Campanha tem dois marcos que não se negociam: a data em que as peças têm
            // que estar prontas e a data em que ela vai ao ar (start_date). Projeto comum
            // ignora este campo — pra ele só importam início e término.
            $table->date('pieces_due_date')->nullable()->after('start_date');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->dropColumn('pieces_due_date');
        });
    }
};

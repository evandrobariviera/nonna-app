<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Varrer de novo" (Evandro, 26/11/2026): a mesma varredura roda outra vez e atualiza a
 * própria linha, em vez de criar uma nova. places_new passa a ser "novas nesta rodada";
 * last_run_at marca o começo da rodada (as empresas criadas depois dela são as novas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_scans', function (Blueprint $table) {
            $table->unsignedInteger('runs')->default(1);
            $table->timestamp('last_run_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commerce_scans', fn (Blueprint $table) => $table->dropColumn(['runs', 'last_run_at']));
    }
};

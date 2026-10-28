<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // Primeiro ciclo criado sozinho quando o cliente nasce (ClientFirstCycleService). A marca
    // deixa apagar cliente duplicado enquanto ninguém mexeu nesse ciclo (Client::blockingAssociations).
    public function up(): void
    {
        Schema::connection('pgsql')->table('macro_plans', function (Blueprint $table) {
            $table->boolean('auto_created')->default(false);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('macro_plans', function (Blueprint $table) {
            $table->dropColumn('auto_created');
        });
    }
};

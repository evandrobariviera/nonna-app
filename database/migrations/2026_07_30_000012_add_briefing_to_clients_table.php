<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            // Panorama corrido do cliente — evolui com o tempo (não é "resultado
            // do diagnóstico", é editável a qualquer momento pela equipe, e no
            // futuro pode ser atualizado periodicamente por IA).
            $table->text('briefing')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            $table->dropColumn('briefing');
        });
    }
};

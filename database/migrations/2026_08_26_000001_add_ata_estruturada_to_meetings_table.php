<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('meetings', function (Blueprint $table) {
            // Versão da ATA reescrita/organizada por IA (ação structure_ata) — a
            // ATA bruta (campo "ata") continua intocada, essa é só a versão apresentável
            // liberada pra equipe quando a reunião entra em Revisão Interna.
            $table->text('ata_estruturada')->nullable()->after('ata');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('meetings', function (Blueprint $table) {
            $table->dropColumn('ata_estruturada');
        });
    }
};

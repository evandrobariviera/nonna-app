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
            // Transcrição bruta da call (colada manualmente, ex: saída de gravação/Whisper) —
            // é a partir DELA que a ação structure_ata gera a ata_estruturada, não da ATA
            // manual (campo "ata", escrita por quem conduziu a reunião, mais curta/editada).
            $table->text('transcricao')->nullable()->after('ata_estruturada');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('meetings', function (Blueprint $table) {
            $table->dropColumn('transcricao');
        });
    }
};

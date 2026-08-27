<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        // Campo único agora: "ata" é sempre a versão atual (gerada/atualizada pelos
        // agentes de IA a partir da Transcrição, editável à mão se precisar ajustar) —
        // ata_estruturada era redundante, existia só há 1 dia e nunca foi lida por nada
        // além do próprio código que a escreveu.
        DB::connection('pgsql')->table('meetings')
            ->whereNotNull('ata_estruturada')
            ->update(['ata' => DB::raw('ata_estruturada')]);

        Schema::connection('pgsql')->table('meetings', function (Blueprint $table) {
            $table->dropColumn('ata_estruturada');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('meetings', function (Blueprint $table) {
            $table->text('ata_estruturada')->nullable()->after('ata');
        });
    }
};

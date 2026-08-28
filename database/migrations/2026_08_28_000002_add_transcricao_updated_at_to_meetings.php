<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca quando a transcrição da reunião foi escrita pela última vez. Serve pro
 * fluxo de Macro/Kickoff: ao virar "realizada", a automação `finalize_macro_meeting`
 * só re-processa a ATA se a transcrição mudou depois da última geração da ATA
 * (transcricao_updated_at > ata_recorded_at) — senão vai direto pro planejamento.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('meetings', function (Blueprint $table) {
            $table->timestamp('transcricao_updated_at')->nullable()->after('transcricao');
        });

        // Backfill: reuniões que já têm transcrição ganham o updated_at da própria
        // linha, pra não parecerem "mais novas que a ATA" indevidamente.
        DB::connection('pgsql')->table('meetings')
            ->whereNotNull('transcricao')
            ->update(['transcricao_updated_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('meetings', function (Blueprint $table) {
            $table->dropColumn('transcricao_updated_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Presença de valor = travado pra geração de insights; motivo é
        // obrigatório na UI (reforça descrever o porquê, não só marcar uma
        // caixinha) — mais confiável do que esperar que a IA infira "não mexe
        // nisso" a partir de um comentário em texto livre.
        Schema::connection('pgsql')->table('ad_campaigns', function (Blueprint $table) {
            $table->text('optimization_locked_reason')->nullable()->after('target_roas');
        });

        Schema::connection('pgsql')->table('ad_adsets', function (Blueprint $table) {
            $table->text('optimization_locked_reason')->nullable()->after('raw_data');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn('optimization_locked_reason');
        });

        Schema::connection('pgsql')->table('ad_adsets', function (Blueprint $table) {
            $table->dropColumn('optimization_locked_reason');
        });
    }
};

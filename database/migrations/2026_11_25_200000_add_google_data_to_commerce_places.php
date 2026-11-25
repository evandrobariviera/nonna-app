<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prospecção: os dados do Google passam a ficar GUARDADOS na empresa (consulta uma vez só).
 *
 * Decisão do Evandro em 25/11/2026, ciente de que os Termos do Google Maps Platform proíbem
 * guardar esse conteúdo (risco aceito: suspensão da chave/faturamento). Muda a regra da
 * migration 2026_11_16_200000 ("nenhuma tabela guarda nome/endereço..."). Motivo: não gastar
 * franquia a cada tela aberta e poder buscar por nome. Atualiza só em "Atualizar dados".
 *
 * google_data: name, address, short_address, type, primary_type, phone, website, has_hours,
 * rating, reviews, photos, maps_url, closed (o que veio da última consulta; a lista grava só
 * name/short_address/type/maps_url/closed antes da coleta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_places', function (Blueprint $table) {
            $table->jsonb('google_data')->nullable();
            $table->timestamp('google_data_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commerce_places', function (Blueprint $table) {
            $table->dropColumn(['google_data', 'google_data_at']);
        });
    }
};

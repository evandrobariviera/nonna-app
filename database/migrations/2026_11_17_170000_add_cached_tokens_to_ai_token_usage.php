<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens que a OpenAI reaproveitou do cache (texto repetido no começo do pedido —
 * instruções, ferramentas, conversa anterior). São cobrados com desconto grande;
 * sem guardar isso o "Uso & Custos" mostrava tudo a preço cheio (2026-11-17,
 * Agente Nonna parecia caro). Coluna nova com default constante = instantânea no Postgres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('ai_token_usage', function (Blueprint $table) {
            $table->integer('cached_tokens')->default(0);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('ai_token_usage', function (Blueprint $table) {
            $table->dropColumn('cached_tokens');
        });
    }
};

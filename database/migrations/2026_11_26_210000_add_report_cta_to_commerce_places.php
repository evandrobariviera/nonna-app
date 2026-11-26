<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raio-x: clique no botão "Quero melhorar meu raio-x" (WhatsApp da agência) — sinal de
 * interesse que aparece na página da empresa. A visita da equipe (logada) não conta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_places', function (Blueprint $table) {
            $table->unsignedInteger('report_cta_clicks')->default(0);
            $table->timestamp('report_cta_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commerce_places', fn (Blueprint $table) => $table->dropColumn(['report_cta_clicks', 'report_cta_at']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raio-x público da prospecção (etapa 5 da Central de Comércio, 26/11/2026).
 * report_token: código do link /rx/{token} (sem hífen — WhatsApp lê UUID com hífen como chave Pix).
 * report_views / report_last_view_at: aberturas pela empresa (a equipe vendo não conta).
 * opted_out_at: quando a empresa pediu para não receber mais mensagens pelo próprio raio-x.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_places', function (Blueprint $table) {
            $table->string('report_token', 40)->nullable()->unique();
            $table->timestamp('report_at')->nullable();
            $table->unsignedInteger('report_views')->default(0);
            $table->timestamp('report_last_view_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commerce_places', function (Blueprint $table) {
            $table->dropUnique(['report_token']);
            $table->dropColumn(['report_token', 'report_at', 'report_views', 'report_last_view_at', 'opted_out_at']);
        });
    }
};

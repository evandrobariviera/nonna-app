<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro que originou o disparo (ex: a oportunidade da Central de Leads que
 * gerou o "Lead Capturado") — pra mostrar os envios dentro do próprio registro.
 * Opcional: disparos sem um registro de origem ficam com null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table) {
            $table->nullableUuidMorphs('related');
        });
    }

    public function down(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table) {
            $table->dropMorphs('related');
        });
    }
};

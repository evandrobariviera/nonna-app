<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Modos da Dashboard (ver App\Support\DashboardModes) — por vínculo pessoa↔organização,
    // igual aos papéis. dashboard_modes null = ainda não configurado (usa o sugerido pelos
    // papéis funcionais); dashboard_mode = último modo usado, pra abrir nele de novo.
    public function up(): void
    {
        Schema::connection('pgsql')->table('organization_users', function (Blueprint $table) {
            $table->jsonb('dashboard_modes')->nullable();
            $table->string('dashboard_mode', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('organization_users', function (Blueprint $table) {
            $table->dropColumn(['dashboard_modes', 'dashboard_mode']);
        });
    }
};

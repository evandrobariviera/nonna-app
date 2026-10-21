<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A versão do diagnóstico é contada por integração (cada WhatsApp do cliente tem
// sua própria série v1, v2...), mas o unique era por cliente — cliente com dois
// números (ex: CFC Esquina Fixo + Celular) não conseguia gerar o v1 do segundo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_diagnostics', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'version']);
            $table->unique(['client_integration_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::table('service_diagnostics', function (Blueprint $table) {
            $table->dropUnique(['client_integration_id', 'version']);
            $table->unique(['client_id', 'version']);
        });
    }
};

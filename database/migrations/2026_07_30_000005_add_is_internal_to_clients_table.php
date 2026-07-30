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
        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            // Identifica o cliente interno da própria agência (usado como fallback
            // no import do ClickUp quando 'cliente_relacionado' não resolve) sem
            // depender de comparar company_name como texto — vira critério de
            // pendência de tarefa (ver AdCampaign/Task::scopePendente).
            $table->boolean('is_internal')->default(false)->after('company_name');
        });

        DB::connection('pgsql')->table('clients')
            ->where('company_name', 'Nonna Agência Digital')
            ->update(['is_internal' => true]);
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            $table->dropColumn('is_internal');
        });
    }
};

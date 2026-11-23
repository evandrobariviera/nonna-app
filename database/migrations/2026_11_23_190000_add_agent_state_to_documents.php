<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trabalho do agente no documento (23/11): situação do rascunho (processando, pronto,
        // erro), as fontes que ele leu e as perguntas que deixou para a equipe.
        $db = Schema::connection('pgsql');
        if (!$db->hasColumn('documents', 'agent_state')) {
            $db->table('documents', function (Blueprint $table) {
                $table->jsonb('agent_state')->nullable()->after('provenance');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('documents', function (Blueprint $table) {
            $table->dropColumn('agent_state');
        });
    }
};

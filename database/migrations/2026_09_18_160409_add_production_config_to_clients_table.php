<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Pessoa da Nonna que organiza e distribui as tarefas deste cliente.
            // NÃO confundir com clients.responsible_name, que é o representante legal
            // do cliente (tem CPF/RG, serve pra contrato) — conceitos opostos, nomes
            // parecidos. Por isso "creative_lead" e não "responsible".
            $table->foreignId('creative_lead_id')->nullable()->after('nickname')
                ->constrained('users')->nullOnDelete();

            // Cota mensal de produção por tipo de tarefa: {"criacao": 12, "web": 4}.
            // Tipo ausente = sem cota definida (não é zero). Jsonb em vez de tabela
            // própria porque é editado como um formulário só, sem metadado por linha
            // nem histórico — mesmo padrão de clients.contracted_services.
            $table->jsonb('production_quota')->nullable()->after('contracted_services');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creative_lead_id');
            $table->dropColumn('production_quota');
        });
    }
};

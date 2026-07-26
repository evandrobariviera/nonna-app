<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('financial_transactions', function (Blueprint $table) {
            // Agrupa um lote gerado de uma vez por recorrência/parcelamento manual.
            // Lançamentos gerados a partir de Contrato usam contract_id como chave
            // de agrupamento — não preenchem esta coluna.
            $table->uuid('recurring_group_id')->nullable()->after('contract_id');
            $table->smallInteger('installment_number')->nullable()->after('recurring_group_id');
            $table->smallInteger('installment_total')->nullable()->after('installment_number');

            $table->index('recurring_group_id');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('financial_transactions', function (Blueprint $table) {
            $table->dropIndex(['recurring_group_id']);
            $table->dropColumn(['recurring_group_id', 'installment_number', 'installment_total']);
        });
    }
};

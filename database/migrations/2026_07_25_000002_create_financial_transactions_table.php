<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('financial_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');

            $table->string('type', 10); // entrada | saida

            $table->foreignUuid('category_id')->nullable()->constrained('financial_categories')->nullOnDelete();

            $table->decimal('amount', 10, 2);
            $table->date('due_date');
            $table->date('paid_at')->nullable();
            $table->string('status', 20)->default('previsto'); // previsto | pago | cancelado ("atrasado" é calculado, não gravado)
            $table->string('payment_method', 20)->nullable(); // pix | cartao | boleto
            $table->string('description', 255)->nullable();

            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('financial_transactions');
    }
};

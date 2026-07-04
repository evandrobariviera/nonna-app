<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();

            $table->string('title');
            $table->string('status', 20)->default('rascunho'); // rascunho | enviado | assinado | ativo | encerrado | cancelado

            // Vigência
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable(); // null = prazo indeterminado
            $table->date('signed_at')->nullable();

            // Financeiro
            $table->decimal('fee_value', 10, 2)->nullable(); // valor recorrente (fee mensal/anual)
            $table->string('fee_type', 20)->nullable(); // mensal | anual | projeto_unico | personalizado
            $table->jsonb('line_items')->nullable(); // itens do contrato: [{descricao, valor, recorrencia}] — preço variável, sem catálogo fixo
            $table->string('payment_method', 20)->nullable(); // pix | cartao | boleto
            $table->unsignedTinyInteger('billing_day')->nullable();

            // Renovação / cancelamento
            $table->string('renewal_type', 20)->nullable(); // automatica | manual | sem_renovacao
            $table->unsignedSmallInteger('notice_period_days')->nullable(); // aviso prévio de cancelamento

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('contracts');
    }
};

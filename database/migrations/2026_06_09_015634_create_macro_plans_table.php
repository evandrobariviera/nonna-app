<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('macro_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');

            $table->string('title', 200);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft'); // draft | active | closed

            // 5 blocos JSONB
            $table->jsonb('bloco1')->nullable(); // foco_principal, verba_anuncios, metas_indicadores
            $table->jsonb('bloco2')->nullable(); // desafio_atual, estrategia, pilares_comunicacao
            // bloco3 = projetos (tabela projects)
            $table->jsonb('bloco4')->nullable(); // tarefas isoladas e rotina
            $table->jsonb('bloco5')->nullable(); // checklist infraestrutura e acessos

            $table->timestamp('launched_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('macro_plans');
    }
};

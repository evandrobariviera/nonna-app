<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Seu foco" — resumo de atenção de cada pessoa, gerado 2x/dia (cedo e meio-dia) pelo
// AttentionDigestService e só LIDO pela Dashboard (ela nunca chama a IA ao abrir).
// items = a lista crua que as regras montaram (o que a IA recebeu); focus = o que a IA
// escreveu já com o link resolvido. Se a IA falhar, focus é montado pelas regras.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attention_digests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('generated_at');
            $table->string('source', 20)->default('ai'); // ai | rules (IA falhou/sem agente) | empty
            $table->text('opening')->nullable();
            $table->jsonb('focus')->nullable();
            $table->text('can_wait')->nullable();
            $table->jsonb('items')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attention_digests');
    }
};

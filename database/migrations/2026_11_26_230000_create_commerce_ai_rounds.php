<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 4 da Central de Comércio — rodadas de IA (26/11/2026).
 * Uma rodada = perguntas que um cliente faria à IA ("qual o melhor dentista em Videira SC?"),
 * cada uma repetida N vezes (a resposta da IA muda a cada vez). Vale para todas as empresas
 * daquele segmento e cidade: quantas respostas citaram cada uma vira a parte "Nas IAs" (20 pts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_ai_rounds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('city_id')->constrained('commerce_cities')->restrictOnDelete();
            $table->foreignUuid('segment_id')->nullable()->constrained('commerce_segments')->nullOnDelete();
            $table->json('questions');
            $table->unsignedSmallInteger('repetitions')->default(3);
            $table->string('provider', 30)->default('chatgpt');
            $table->string('status', 20)->default('queued'); // queued | running | done | error
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'city_id', 'segment_id']);
        });

        Schema::create('commerce_ai_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('round_id')->constrained('commerce_ai_rounds')->cascadeOnDelete();
            $table->unsignedSmallInteger('question_index');
            $table->unsignedSmallInteger('repetition');
            $table->text('answer')->nullable();
            $table->json('names')->nullable();   // nomes que a IA citou (linha NOMES: da resposta)
            $table->json('sources')->nullable(); // links que a busca usou
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['round_id', 'question_index', 'repetition']);
        });

        Schema::create('commerce_ai_round_place', function (Blueprint $table) {
            $table->foreignUuid('round_id')->constrained('commerce_ai_rounds')->cascadeOnDelete();
            $table->foreignUuid('place_id')->constrained('commerce_places')->cascadeOnDelete();
            $table->unsignedSmallInteger('mentions')->default(0); // em quantas respostas apareceu
            $table->primary(['round_id', 'place_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_ai_round_place');
        Schema::dropIfExists('commerce_ai_answers');
        Schema::dropIfExists('commerce_ai_rounds');
    }
};

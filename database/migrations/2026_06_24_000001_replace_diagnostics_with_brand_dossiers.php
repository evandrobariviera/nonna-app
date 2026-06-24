<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old tables (FK order: filhas antes da pai)
        Schema::dropIfExists('diagnostic_personas');
        Schema::dropIfExists('diagnostic_competitors');
        Schema::dropIfExists('client_diagnostics');

        // ── brand_dossiers (substitui client_diagnostics) ──
        Schema::create('brand_dossiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('title', 200)->nullable();
            $table->string('fase', 30)->default('pesquisa_previa');
            // fases: pesquisa_previa | kickoff | pesquisa_estrategica | redacao | aprovado
            $table->date('data_kickoff')->nullable();
            $table->foreignId('estrategista_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('redator_id')->nullable()->constrained('users')->nullOnDelete();

            // ── ZONA 1 — Roteiro (dados brutos) ──
            $table->jsonb('roteiro_pesquisa_previa')->nullable();
            $table->jsonb('roteiro_kickoff_negocio')->nullable();
            $table->jsonb('roteiro_kickoff_mercado')->nullable();
            $table->jsonb('roteiro_kickoff_cliente')->nullable();
            $table->jsonb('roteiro_kickoff_comunicacao')->nullable();
            $table->text('roteiro_pesquisa_notas')->nullable();

            // ── ZONA 2 — Bloco 01: Diagnóstico ──
            $table->text('bloco01_visao_geral')->nullable();
            $table->text('bloco01_historico')->nullable();
            $table->text('bloco01_pontos_fortes')->nullable();
            $table->text('bloco01_pontos_atencao')->nullable();
            $table->text('bloco01_objetivos')->nullable();
            $table->text('bloco01_contexto_mercado')->nullable();
            $table->text('bloco01_como_compra')->nullable();
            $table->text('bloco01_gera_confianca')->nullable();
            $table->text('bloco01_espaco_disponivel')->nullable();
            $table->text('bloco01_comunicacao_atual')->nullable();
            $table->text('bloco01_percepcao_atual')->nullable();
            $table->text('bloco01_gap_percepcao')->nullable();

            // ── ZONA 2 — Bloco 02: Estratégia ──
            $table->text('bloco02_problema')->nullable();
            $table->text('bloco02_problema_justificativa')->nullable();
            $table->text('bloco02_oportunidade')->nullable();
            $table->text('bloco02_insight')->nullable();
            $table->text('bloco02_insight_sustentacao')->nullable();
            $table->jsonb('bloco02_objetivos')->nullable(); // {pensar, sentir, fazer}

            // ── ZONA 2 — Bloco 03: Plataforma de Marca ──
            $table->text('bloco03_proposito')->nullable();
            $table->text('bloco03_visao')->nullable();
            $table->text('bloco03_missao')->nullable();
            $table->jsonb('bloco03_valores')->nullable();             // [{nome, descricao}, ...]
            $table->jsonb('bloco03_posicionamento_formula')->nullable(); // {publico, marca, categoria, diferencial, razao}
            $table->text('bloco03_posicionamento_texto')->nullable();
            $table->text('bloco03_promessa')->nullable();
            $table->text('bloco03_razoes')->nullable();
            $table->text('bloco03_personalidade')->nullable();
            $table->jsonb('bloco03_arquetipo')->nullable();           // {nome, icone, descricao}
            $table->text('bloco03_essencia')->nullable();
            $table->text('bloco03_essencia_descricao')->nullable();

            // ── ZONA 2 — Bloco 04: Território ──
            $table->text('bloco04_tom_voz')->nullable();
            $table->jsonb('bloco04_exemplos_tom')->nullable();        // [{correto, incorreto}, ...]
            $table->jsonb('bloco04_territorio_visual')->nullable();   // {cores, tipografia, foto, linguagem}
            $table->text('bloco04_palavras_prioritarias')->nullable();
            $table->text('bloco04_palavras_secundarias')->nullable();
            $table->text('bloco04_palavras_evitar')->nullable();
            $table->text('bloco04_frases_ancora')->nullable();
            $table->text('bloco04_mandatorios')->nullable();
            $table->text('bloco04_restricoes')->nullable();
            $table->text('bloco04_teste_final')->nullable();

            // ── Metadata ──
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // ── dossier_competitors (substitui diagnostic_competitors) ──
        Schema::create('dossier_competitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dossier_id')->constrained('brand_dossiers')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('nome', 200);
            $table->text('o_que_comunica')->nullable();
            $table->text('como_se_posiciona')->nullable();
            $table->text('o_que_nao_ocupa')->nullable();
            $table->timestamps();
        });

        // ── dossier_personas (substitui diagnostic_personas) ──
        Schema::create('dossier_personas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dossier_id')->constrained('brand_dossiers')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('tipo', 20)->default('principal'); // principal | secundaria
            $table->string('nome_ficticio', 100)->nullable();
            $table->string('idade_genero', 100)->nullable();
            $table->string('cargo_setor', 200)->nullable();
            $table->string('renda_contexto', 200)->nullable();
            $table->text('como_se_informa')->nullable();
            $table->text('o_que_valida_compra')->nullable();
            $table->text('dores_principais')->nullable();
            $table->text('o_que_motiva')->nullable();
            $table->text('o_que_nunca_diria')->nullable();
            $table->text('insight_persona')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossier_personas');
        Schema::dropIfExists('dossier_competitors');
        Schema::dropIfExists('brand_dossiers');
    }
};

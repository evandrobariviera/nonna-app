<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mapa dos Documentos (Central de Ajuda) — os documentos padrão da agência, por etapa,
        // com a ficha de cada um (quando, quem faz, quem revisa, com o quê, próximo passo),
        // o modelo HTML e o status da padronização. Alimentado por `conteudo:publicar`.
        Schema::connection('pgsql')->create('document_standards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');

            $table->string('slug');
            $table->string('name');
            $table->string('stage', 30);
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('status', 20)->default('na_fila');

            $table->text('summary')->nullable();
            $table->string('when_made')->nullable();
            $table->string('made_by')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->text('inputs')->nullable();
            $table->string('next_step')->nullable();

            $table->longText('template_html')->nullable();
            $table->string('help_article_slug')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('document_standards');
    }
};

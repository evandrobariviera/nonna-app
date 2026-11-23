<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('pgsql');

        // Documentos em campos (23/11): o documento padrão guardado campo a campo, seguindo o
        // modelo do tipo (App\Services\Documents\Schemas\*). O HTML vira só a visualização.
        // Primeiro tipo: planejamento (preso ao MacroPlan pelo documentable).
        if (!$db->hasTable('documents')) {
            $db->create('documents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
                $table->uuid('client_id');
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');

                $table->string('kind', 40);                       // planejamento, campanha, projeto...
                $table->unsignedSmallInteger('schema_version')->default(1);
                $table->string('documentable_type')->nullable();
                $table->uuid('documentable_id')->nullable();

                $table->jsonb('content');                          // só os valores, no formato do modelo
                $table->jsonb('provenance')->nullable();           // caminho → {by: pessoa|agente, user_id, at}

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index(['documentable_type', 'documentable_id']);
                $table->index(['client_id', 'kind']);
            });
        }

        // Histórico de mudanças por campo (quem, quando, antes/depois). Edições seguidas da
        // mesma pessoa no mesmo campo se juntam numa linha só (DocumentEditor).
        if (!$db->hasTable('document_revisions')) {
            $db->create('document_revisions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('document_id');
                $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('actor', 20)->default('pessoa');    // pessoa | agente
                $table->string('action', 30);                      // campo, adicionar, remover, duplicar, mover, tipo
                $table->string('path');
                $table->jsonb('before')->nullable();
                $table->jsonb('after')->nullable();
                $table->timestamps();

                $table->index(['document_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('document_revisions');
        Schema::connection('pgsql')->dropIfExists('documents');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tarefa criada a partir de uma linha do documento em campos (23/11, etapa D): a peça da
        // campanha ou a entrega do projeto. Guarda o id da linha para não criar a mesma tarefa
        // duas vezes e para a tela mostrar "já virou tarefa". Coluna nula, sem índice (a busca
        // sempre filtra antes por project_id): no Postgres é só metadado, não reescreve a tabela.
        $db = Schema::connection('pgsql');
        if (!$db->hasColumn('tasks', 'document_item_id')) {
            $db->table('tasks', function (Blueprint $table) {
                $table->string('document_item_id', 16)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->dropColumn('document_item_id');
        });
    }
};

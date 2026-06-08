<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel cria check constraints para colunas enum no PostgreSQL
        // Precisa dropar os constraints antes de alterar a coluna
        DB::statement("ALTER TABLE contacts DROP CONSTRAINT IF EXISTS contacts_status_check");
        DB::statement("ALTER TABLE contacts DROP CONSTRAINT IF EXISTS contacts_source_check");

        // Converte para VARCHAR simples
        DB::statement("ALTER TABLE contacts ALTER COLUMN status TYPE VARCHAR(20)");
        DB::statement("ALTER TABLE contacts ALTER COLUMN source TYPE VARCHAR(30)");

        // Migra todos os valores de status para 'ativo'
        // (ganho, novo, qualificado, em_negociacao, perdido → ativo | inativo → inativo)
        DB::statement("UPDATE contacts SET status = 'ativo' WHERE status <> 'inativo'");
        DB::statement("UPDATE contacts SET status = 'inativo' WHERE status = 'inativo'");
    }

    public function down(): void
    {
        // Não reversível de forma segura — manter varchar
    }
};

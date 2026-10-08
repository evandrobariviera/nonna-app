<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Conversa do Assistente de Lançamento de Tarefas passa a ser de cada
    // pessoa (user_id) — antes era uma por Projeto, então duas pessoas no
    // mesmo projeto dividiam o histórico e "Nova conversa" apagava a do outro.
    // O chat de Tarefa (tasks/show) segue compartilhado, com user_id null.
    // Unicidade vira 2 índices parciais: (tipo, entidade) quando user_id é
    // null, e (tipo, entidade, user) quando não é.
    public function up(): void
    {
        Schema::connection('pgsql')->table('ai_chats', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->dropUnique(['entity_type', 'entity_id']);
        });

        DB::connection('pgsql')->statement('CREATE UNIQUE INDEX ai_chats_shared_unique ON ai_chats (entity_type, entity_id) WHERE user_id IS NULL');
        DB::connection('pgsql')->statement('CREATE UNIQUE INDEX ai_chats_per_user_unique ON ai_chats (entity_type, entity_id, user_id) WHERE user_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP INDEX IF EXISTS ai_chats_per_user_unique');
        DB::connection('pgsql')->statement('DROP INDEX IF EXISTS ai_chats_shared_unique');
        DB::connection('pgsql')->table('ai_chats')->whereNotNull('user_id')->delete();

        Schema::connection('pgsql')->table('ai_chats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->unique(['entity_type', 'entity_id']);
        });
    }
};

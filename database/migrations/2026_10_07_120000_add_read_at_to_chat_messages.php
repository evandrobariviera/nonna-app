<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Confirmação de leitura (✓ / ✓✓ "Lida em...") nas conversas individuais.
 *
 * chat_participants.last_read_message_id já diz ATÉ ONDE a pessoa leu, mas não QUANDO.
 * Na conversa individual só existe 1 leitor possível por mensagem (o outro), então a
 * hora cabe na própria mensagem. Mensagens antigas ficam null — lidas, mas sem hora
 * (o ✓✓ continua vindo do last_read_message_id). Setor/grupo não usa (precisaria de
 * uma linha por leitor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('edited_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Chat interno da equipe (widget flutuante + tela /chat) — ver
 * .claude/docs/internal-chat-plan.md.
 *
 * Regra de privacidade (decisão do Evandro): só quem participa da conversa lê.
 * Nem dono, nem admin, nem superadmin têm atalho — por isso chat_participants é
 * a única porta de acesso (ver ChatConversationPolicy).
 *
 * Mensagens usam id sequencial (bigint), não uuid: o polling do widget pede "o que
 * chegou depois do id X", e timestamp com precisão de segundo perderia mensagens
 * enviadas no mesmo segundo. O mesmo id serve de marcador de leitura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();

            $table->string('type', 20); // direct | sector | group

            // Canal de Setor. Se o Setor for apagado, a conversa fica como histórico
            // (sem sincronizar membros) em vez de sumir com as mensagens.
            $table->foreignUuid('sector_id')->nullable()->constrained('sectors')->nullOnDelete();

            // Conversa individual: "menorId:maiorId" — garante uma única conversa por par.
            $table->string('direct_key', 50)->nullable();

            $table->string('name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'direct_key']);
            $table->index(['organization_id', 'last_message_at']);
        });

        Schema::create('chat_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Id da última mensagem lida — tudo acima disso (de outra pessoa) é não lido.
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->boolean('muted')->default(false);
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();

            // Quem sai da empresa: a mensagem fica, sem autor ("Usuário removido").
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body')->nullable(); // texto puro — formatado (escapado) só na exibição
            $table->unsignedBigInteger('reply_to_id')->nullable(); // Etapa 3 (responder)
            $table->timestamp('edited_at')->nullable();           // Etapa 3 (editar)
            $table->softDeletes();                                 // Etapa 3 (apagar)
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('chat_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->string('filename');
            $table->string('disk_path');
            $table->string('disk', 20);
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        $this->createChannelsForExistingSectors();
    }

    // Setores que já existiam antes do chat ganham o canal agora; os novos são criados
    // por SectorController (ChatService::syncSectorChannel).
    private function createChannelsForExistingSectors(): void
    {
        $now = now();

        foreach (DB::table('sectors')->get(['id', 'organization_id', 'name']) as $sector) {
            $conversationId = (string) Str::uuid();

            DB::table('chat_conversations')->insert([
                'id'              => $conversationId,
                'organization_id' => $sector->organization_id,
                'type'            => 'sector',
                'sector_id'       => $sector->id,
                'name'            => $sector->name,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            $userIds = DB::table('sector_user')->where('sector_id', $sector->id)->pluck('user_id');

            foreach ($userIds as $userId) {
                DB::table('chat_participants')->insert([
                    'conversation_id' => $conversationId,
                    'user_id'         => $userId,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_attachments');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_participants');
        Schema::dropIfExists('chat_conversations');
    }
};

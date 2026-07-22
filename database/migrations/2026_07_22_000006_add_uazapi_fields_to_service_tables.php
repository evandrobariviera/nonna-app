<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('service_conversations', function (Blueprint $table) {
            $table->boolean('is_group')->default(false)->after('contact_phone');
        });

        Schema::connection('pgsql')->table('service_messages', function (Blueprint $table) {
            // id atômico da mensagem na origem (messageid da uazapi) - garante ingestão idempotente
            $table->string('external_message_id')->nullable()->after('conversation_id');
            // mensagem disparada por automação/bot (wasSentByApi), não por atendente humano
            $table->boolean('sent_via_api')->default(false)->after('direction');

            $table->unique(['conversation_id', 'external_message_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('service_messages', function (Blueprint $table) {
            $table->dropUnique(['conversation_id', 'external_message_id']);
            $table->dropColumn(['external_message_id', 'sent_via_api']);
        });

        Schema::connection('pgsql')->table('service_conversations', function (Blueprint $table) {
            $table->dropColumn('is_group');
        });
    }
};

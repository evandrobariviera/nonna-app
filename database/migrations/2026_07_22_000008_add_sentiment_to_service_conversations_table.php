<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('service_conversations', function (Blueprint $table) {
            // Sentimento da conversa (0-100), calculado pela IA a partir do teor das mensagens
            $table->decimal('sentiment_score', 5, 2)->nullable()->after('is_group');
            // Trajetória dentro da própria conversa - não só o estado final
            $table->string('sentiment_trend', 20)->nullable()->after('sentiment_score'); // melhorando | piorando | estavel
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('service_conversations', function (Blueprint $table) {
            $table->dropColumn(['sentiment_score', 'sentiment_trend']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('service_diagnostics', function (Blueprint $table) {
            // Índice único de Atendimento (0-100), composto por velocidade/conversão/consistência/sentimento
            $table->unsignedTinyInteger('service_score')->nullable()->after('avg_first_response_minutes');
            $table->jsonb('service_score_breakdown')->nullable()->after('service_score');

            // Sentimento médio das conversas do período (0-100)
            $table->decimal('avg_sentiment_score', 5, 2)->nullable()->after('service_score_breakdown');

            // Estimativa de receita perdida no período, calculada a partir dos gaps
            // (leads perdidos estimados x ticket médio configurado na integração)
            $table->decimal('estimated_lost_revenue', 12, 2)->nullable()->after('avg_sentiment_score');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('service_diagnostics', function (Blueprint $table) {
            $table->dropColumn(['service_score', 'service_score_breakdown', 'avg_sentiment_score', 'estimated_lost_revenue']);
        });
    }
};

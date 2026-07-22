<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('service_diagnostics', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('client_integration_id')->nullable()->constrained('client_integrations')->nullOnDelete();

            $table->unsignedSmallInteger('version')->default(1);
            $table->date('period_start');
            $table->date('period_end');

            $table->string('status', 20)->default('draft'); // draft | published
            $table->string('generated_by', 20)->default('manual'); // scheduled | manual
            $table->foreignUuid('ai_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // KPIs explícitos - comparáveis entre versões (evolução ao longo do tempo)
            $table->unsignedInteger('total_conversations')->default(0);
            $table->unsignedInteger('sales_confirmed')->default(0);
            $table->unsignedInteger('sales_in_negotiation')->default(0);
            $table->decimal('conversion_rate', 5, 2)->nullable();
            $table->unsignedInteger('avg_first_response_minutes')->nullable();

            // narrativa estruturada (seções do diagnóstico)
            $table->jsonb('funnel_summary')->nullable();     // panorama + tabela de estágios
            $table->jsonb('gaps')->nullable();                // gaps operacionais
            $table->jsonb('strengths')->nullable();           // o que funciona
            $table->jsonb('campaign_insights')->nullable();   // insights de campanha
            $table->jsonb('recommendations')->nullable();     // quick wins + estruturais

            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('service_diagnostics');
    }
};

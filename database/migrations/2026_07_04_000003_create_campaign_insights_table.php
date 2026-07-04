<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('campaign_insights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');

            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('client_ad_account_id')->nullable()->constrained('client_ad_accounts')->cascadeOnDelete();
            $table->uuid('ad_campaign_id')->nullable();
            $table->foreign('ad_campaign_id')->references('id')->on('ad_campaigns')->nullOnDelete();

            $table->string('kind', 30); // budget_overrun | budget_idle | cpa_spike | ctr_drop | roas_drop
            $table->string('severity', 20); // info | atencao | critico
            $table->string('status', 20)->default('novo'); // novo | lido | resolvido | descartado

            $table->string('title', 255);
            $table->text('summary')->nullable(); // narrativa gerada pela IA
            $table->jsonb('metrics_snapshot')->nullable();

            $table->foreignUuid('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('campaign_insights');
    }
};

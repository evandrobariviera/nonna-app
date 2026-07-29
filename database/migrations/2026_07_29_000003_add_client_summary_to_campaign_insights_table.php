<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('campaign_insights', function (Blueprint $table) {
            // Narrativa em linguagem de resultado, sem jargão — versão do
            // "summary" pra mostrar no Portal do Cliente. Só preenchida pros
            // kinds em CampaignInsightService::CLIENT_VISIBLE_KINDS.
            $table->text('client_summary')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('campaign_insights', function (Blueprint $table) {
            $table->dropColumn('client_summary');
        });
    }
};

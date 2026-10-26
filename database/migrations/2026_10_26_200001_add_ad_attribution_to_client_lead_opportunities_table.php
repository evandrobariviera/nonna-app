<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Liga a entrada do lead ao anúncio/campanha que já sincronizamos da Meta/Google.
// Os IDs da plataforma ficam guardados mesmo quando o anúncio ainda não foi
// sincronizado (ad_ad_id/ad_campaign_id null) — dá pra religar depois.
// service_conversation_id: a conversa do WhatsApp que gerou a entrada.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('client_lead_opportunities', function (Blueprint $table) {
            $table->string('ad_external_id', 64)->nullable()->after('ctwa_clid');
            $table->string('campaign_external_id', 64)->nullable()->after('ad_external_id');
            $table->foreignUuid('ad_ad_id')->nullable()->after('campaign_external_id')
                ->constrained('ad_ads')->nullOnDelete();
            $table->foreignUuid('ad_campaign_id')->nullable()->after('ad_ad_id')
                ->constrained('ad_campaigns')->nullOnDelete();
            $table->foreignUuid('service_conversation_id')->nullable()->after('ad_campaign_id')
                ->constrained('service_conversations')->nullOnDelete();

            $table->index('ad_campaign_id');
            $table->index('service_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_lead_opportunities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_conversation_id');
            $table->dropConstrainedForeignId('ad_campaign_id');
            $table->dropConstrainedForeignId('ad_ad_id');
            $table->dropColumn(['ad_external_id', 'campaign_external_id']);
        });
    }
};

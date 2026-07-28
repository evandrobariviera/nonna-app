<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('campaign_insights', function (Blueprint $table) {
            $table->uuid('ad_adset_id')->nullable()->after('ad_campaign_id');
            $table->foreign('ad_adset_id')->references('id')->on('ad_adsets')->nullOnDelete();

            $table->uuid('ad_ad_id')->nullable()->after('ad_adset_id');
            $table->foreign('ad_ad_id')->references('id')->on('ad_ads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('campaign_insights', function (Blueprint $table) {
            $table->dropForeign(['ad_adset_id']);
            $table->dropForeign(['ad_ad_id']);
            $table->dropColumn(['ad_adset_id', 'ad_ad_id']);
        });
    }
};

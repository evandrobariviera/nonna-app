<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('ad_adsets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('ad_campaign_id');

            $table->string('platform');
            $table->string('external_id')->index();  // ID do adset na plataforma
            $table->string('campaign_external_id'); // ref direta para joins eficientes
            $table->string('name');
            $table->string('status')->default('active');
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->decimal('lifetime_budget', 12, 2)->nullable();
            $table->jsonb('targeting')->nullable();  // segmentação
            $table->jsonb('raw_data')->nullable();

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('ad_campaign_id')->references('id')->on('ad_campaigns')->onDelete('cascade');
            $table->unique(['ad_campaign_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('ad_adsets');
    }
};

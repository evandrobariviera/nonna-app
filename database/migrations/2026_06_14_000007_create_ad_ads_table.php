<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('ad_ads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('ad_adset_id');

            $table->string('platform');
            $table->string('external_id')->index();
            $table->string('adset_external_id');
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('creative_type')->nullable(); // image | video | carousel | story
            $table->string('creative_url')->nullable();  // URL do criativo (R2 futuramente)
            $table->jsonb('raw_data')->nullable();

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('ad_adset_id')->references('id')->on('ad_adsets')->onDelete('cascade');
            $table->unique(['ad_adset_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('ad_ads');
    }
};

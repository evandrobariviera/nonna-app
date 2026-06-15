<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('ad_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_ad_account_id');

            $table->string('platform');              // meta | google
            $table->string('external_id')->index(); // ID da campanha na plataforma
            $table->string('name');
            $table->string('status')->default('active'); // active | paused | deleted | archived
            $table->string('objective')->nullable(); // awareness | traffic | leads | sales...
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->jsonb('raw_data')->nullable();   // payload completo da API

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('client_ad_account_id')->references('id')->on('client_ad_accounts')->onDelete('cascade');
            $table->unique(['client_ad_account_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('ad_campaigns');
    }
};

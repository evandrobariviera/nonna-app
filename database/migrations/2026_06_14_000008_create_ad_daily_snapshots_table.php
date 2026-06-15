<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('ad_daily_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_ad_account_id');

            // Nível e entidade
            $table->string('entity_level');            // campaign | adset | ad
            $table->string('entity_id');               // external ID da entidade
            $table->string('entity_name');
            $table->string('parent_entity_id')->nullable(); // campaign_id para adset; adset_id para ad
            $table->string('platform');                // meta | google

            // Período
            $table->date('snapshot_date');

            // Métricas financeiras
            $table->decimal('spend', 14, 4)->default(0);
            $table->decimal('revenue', 14, 4)->default(0); // valor convertido (para ROAS)

            // Métricas de volume
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);

            // Métricas calculadas (persistidas para performance de query)
            $table->decimal('cpm', 10, 4)->nullable();   // custo por mil impressões
            $table->decimal('cpc', 10, 4)->nullable();   // custo por clique
            $table->decimal('ctr', 8, 4)->nullable();    // click-through rate (%)
            $table->decimal('cpa', 10, 4)->nullable();   // custo por aquisição
            $table->decimal('roas', 10, 4)->nullable();  // return on ad spend

            // Payload completo — não perder nenhum dado da API
            $table->jsonb('raw_data')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('client_ad_account_id')->references('id')->on('client_ad_accounts')->onDelete('cascade');

            // Evita duplicatas por nível + entidade + data
            $table->unique(['client_ad_account_id', 'entity_level', 'entity_id', 'snapshot_date'], 'ad_snapshots_unique');

            $table->index(['organization_id', 'snapshot_date']);
            $table->index(['client_ad_account_id', 'entity_level', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('ad_daily_snapshots');
    }
};

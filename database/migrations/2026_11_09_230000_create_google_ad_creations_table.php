<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anúncio de Pesquisa (responsivo) criado pelo App num grupo de anúncios que
 * já existe no Google (Central de Campanhas, etapa 5). Nasce PAUSADO.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('google_ad_creations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('ad_campaign_id')->index();
            $table->uuid('client_ad_account_id');
            $table->string('ad_group_id', 30);
            $table->string('ad_group_name')->nullable();
            $table->string('ad_id', 30)->nullable()->index();
            $table->string('ad_resource')->nullable();
            $table->string('status', 15)->default('pausado'); // pausado | ativo | erro
            $table->jsonb('headlines');
            $table->jsonb('descriptions');
            $table->string('path1', 15)->nullable();
            $table->string('path2', 15)->nullable();
            $table->text('final_url');
            $table->boolean('ai_suggested')->default(false);
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->foreign('ad_campaign_id')->references('id')->on('ad_campaigns')->cascadeOnDelete();
            $table->foreign('client_ad_account_id')->references('id')->on('client_ad_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('google_ad_creations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campanha completa criada pelo App na Meta (Central de Campanhas): campanha +
 * conjunto (+ anúncio opcional, em meta_ad_creations). Tudo nasce PAUSADO;
 * "Ativar" liga e confere de novo o teto da verba do mês.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('meta_campaign_creations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('client_id')->index();
            $table->uuid('client_ad_account_id');
            $table->string('kind', 20); // mensagens | trafego | reconhecimento
            $table->string('campaign_name');
            $table->string('adset_name');
            $table->decimal('daily_budget', 12, 2);
            $table->boolean('housing')->default(false);
            $table->jsonb('audience'); // cidade, raio, idade, gênero (o que o gestor escolheu)
            $table->string('campaign_id', 50)->nullable()->index();
            $table->string('adset_id', 50)->nullable();
            $table->uuid('meta_ad_creation_id')->nullable();
            $table->string('status', 15)->default('pausado'); // pausado | ativo | erro
            $table->text('error')->nullable();
            $table->text('ad_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->foreign('client_ad_account_id')->references('id')->on('client_ad_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('meta_campaign_creations');
    }
};

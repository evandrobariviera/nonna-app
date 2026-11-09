<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arte aprovada → anúncio na Meta (Central de Campanhas, etapa 3). Cada
 * anúncio criado pelo App: de qual tarefa/arte veio, onde foi criado, o texto
 * usado e quem ativou. Todo anúncio nasce PAUSADO.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('meta_ad_creations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('task_id')->index();
            $table->uuid('task_attachment_id')->nullable();
            $table->uuid('client_ad_account_id');
            $table->string('campaign_external_id', 50)->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('adset_external_id', 50);
            $table->string('adset_name')->nullable();
            $table->string('template_ad_id', 50)->nullable();
            $table->string('ad_id', 50)->nullable()->index();
            $table->string('creative_id', 50)->nullable();
            $table->string('ad_name')->nullable();
            $table->string('status', 15)->default('pausado'); // pausado | ativo | erro
            $table->text('message')->nullable();
            $table->string('headline')->nullable();
            $table->string('cta_type', 40)->nullable();
            $table->text('link')->nullable();
            $table->boolean('caption_changed')->default(false);
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('client_ad_account_id')->references('id')->on('client_ad_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('meta_ad_creations');
    }
};

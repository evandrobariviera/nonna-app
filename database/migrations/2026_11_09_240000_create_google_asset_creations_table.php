<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arte aprovada → imagem num grupo de recursos da Performance Max (Central de
 * Campanhas, etapa 5b). Uma linha por imagem. O vínculo nasce PAUSADO.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('google_asset_creations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('task_id')->index();
            $table->uuid('task_attachment_id')->nullable();
            $table->uuid('client_ad_account_id');
            $table->string('campaign_external_id', 30)->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('asset_group_id', 30);
            $table->string('asset_group_name')->nullable();
            $table->string('asset_resource')->nullable();
            $table->string('link_resource')->nullable();
            $table->string('field_type', 40);
            $table->string('status', 15)->default('pausado'); // pausado | ativo | erro
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
        Schema::connection('pgsql')->dropIfExists('google_asset_creations');
    }
};

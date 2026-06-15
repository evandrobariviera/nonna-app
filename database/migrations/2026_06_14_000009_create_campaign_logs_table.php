<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('campaign_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_ad_account_id');

            // A qual entidade o log se refere
            $table->string('entity_level'); // campaign | adset | ad
            $table->string('entity_id');    // external platform ID
            $table->string('entity_name'); // snapshot do nome na hora do log
            $table->string('platform');    // meta | google

            // Quem registrou
            $table->unsignedBigInteger('logged_by'); // FK users

            // O que aconteceu
            // budget_change | targeting | creative | pause | resume | bid | note | other
            $table->string('type');
            $table->text('description');

            // Dados estruturados do que mudou (antes/depois, valores, etc.)
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('client_ad_account_id')->references('id')->on('client_ad_accounts')->onDelete('cascade');
            $table->foreign('logged_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['organization_id', 'entity_level', 'entity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('campaign_logs');
    }
};

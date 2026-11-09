<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas e conexões (Central de Campanhas): o resultado da verificação diária
 * de cada conta de anúncio — Página e Instagram ligados (Meta), acesso do BM
 * da Nonna, UTM no padrão (anúncios Meta / conta Google). Tabela própria em
 * vez de colunas em client_ad_accounts, que é lida o tempo todo.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('ad_account_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_ad_account_id')->unique();
            $table->string('status', 20);               // ok | atencao | sem_acesso | erro
            $table->jsonb('issues')->default('[]');      // o que falta, em frases curtas
            $table->string('page_id', 50)->nullable();
            $table->string('page_name', 150)->nullable();
            $table->string('instagram_id', 50)->nullable();
            $table->string('instagram_username', 100)->nullable();
            $table->string('utm_status', 20)->nullable(); // ok | diferente | faltando
            $table->text('utm_current')->nullable();
            $table->unsignedInteger('ads_checked')->nullable();
            $table->unsignedInteger('ads_off_standard')->nullable();
            $table->boolean('auto_tagging')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->foreign('client_ad_account_id')->references('id')->on('client_ad_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('ad_account_connections');
    }
};

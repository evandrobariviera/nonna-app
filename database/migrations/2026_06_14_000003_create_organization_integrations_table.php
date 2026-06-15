<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('organization_integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');

            // meta | google | tiktok | linkedin | pinterest
            // clickup | n8n | whatsapp | slack | ga4 | looker
            $table->string('provider');

            $table->string('label');               // "BM Principal", "Google MCC", "ClickUp Workspace"
            $table->string('external_id')->nullable(); // BM ID, MCC ID, Team ID — chave de correlação externa

            // Credenciais sensíveis — armazenadas encriptadas (Laravel encrypt())
            $table->text('credentials')->nullable();

            // Config não-sensível (currency, timezone, padrões)
            $table->jsonb('settings')->nullable();

            $table->string('status')->default('pending'); // connected | disconnected | error | pending
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();   // para tokens OAuth com expiração
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index(['organization_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('organization_integrations');
    }
};

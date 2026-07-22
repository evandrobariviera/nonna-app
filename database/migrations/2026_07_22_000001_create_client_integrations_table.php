<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('client_integrations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();

            // uazapi | partner_crm
            $table->string('provider', 30);
            $table->string('label')->nullable();       // "WhatsApp Comercial", "CRM XPTO"
            $table->string('external_id')->nullable(); // instance_id da uazapi / account id do CRM parceiro

            // Credenciais sensíveis - armazenadas encriptadas (Crypt::encryptString)
            $table->text('credentials')->nullable();

            // Config não-sensível (webhook_secret, diagnostic_frequency_days, etc.)
            $table->jsonb('settings')->nullable();

            $table->string('status', 20)->default('pending'); // pending | connected | disconnected | error
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();

            $table->timestamps();

            $table->index(['client_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('client_integrations');
    }
};

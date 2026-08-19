<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('client_lead_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('lead_channel_id')->constrained('lead_channels')->restrictOnDelete();

            $table->string('external_id', 150); // domínio do site, Form ID do Meta Lead Ads, número de WhatsApp etc.
            $table->string('label', 150)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['lead_channel_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('client_lead_sources');
    }
};

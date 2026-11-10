<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rastreamento por cliente (substitui o GTM web + server): para onde o App
 * manda as conversões e o que a Nonna Tag carrega no site.
 * - client_trackings: configuração de cada cliente (segredos criptografados).
 * - conversion_events: cada envio de conversão (Meta, Google Ads, GA4), com
 *   event_id para não contar duas vezes; nunca manda o mesmo evento 2x.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('client_trackings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('client_id')->unique();
            $table->string('meta_pixel_id', 30)->nullable();
            $table->string('meta_test_event_code', 30)->nullable();   // modo teste da Meta
            $table->string('ga4_measurement_id', 30)->nullable();     // G-XXXXXXX
            $table->text('ga4_api_secret')->nullable();               // criptografado
            $table->uuid('google_ads_account_id')->nullable();        // client_ad_accounts.id
            $table->string('google_lead_action', 120)->nullable();    // customers/X/conversionActions/Y
            $table->string('google_purchase_action', 120)->nullable();
            $table->string('clarity_id', 30)->nullable();
            $table->string('site_key', 40)->unique();                 // chave pública da Nonna Tag
            $table->boolean('send_enabled')->default(false);          // liga os envios de verdade
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });

        Schema::connection('pgsql')->create('conversion_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('client_id')->index();
            $table->uuid('client_lead_opportunity_id')->nullable()->index();
            $table->string('platform', 10);    // meta | google | ga4
            $table->string('event_name', 40);  // Lead | QualifiedLead | Purchase
            $table->string('event_id', 120);
            $table->string('status', 15);      // enviado | teste | erro | ignorado
            $table->text('detail')->nullable();
            $table->decimal('value', 12, 2)->nullable();
            $table->timestamp('event_time');
            $table->timestamps();

            $table->unique(['client_lead_opportunity_id', 'platform', 'event_name'], 'conversion_events_once');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('conversion_events');
        Schema::connection('pgsql')->dropIfExists('client_trackings');
    }
};

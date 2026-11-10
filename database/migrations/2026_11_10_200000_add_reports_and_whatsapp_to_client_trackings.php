<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rastreamento, parte 2:
 * - ga4_property_id / search_console_site: de onde o App lê os números do site
 *   (visitas, buscas no Google) quando o Google for reconectado com essas permissões;
 * - whatsapp_waba_id: conta do WhatsApp Business ligada ao conjunto de dados da
 *   Meta, para devolver lead/venda das conversas de anúncio (ctwa_clid).
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('client_trackings', function (Blueprint $table) {
            $table->string('ga4_property_id', 20)->nullable()->after('ga4_api_secret');
            $table->string('search_console_site', 255)->nullable()->after('ga4_property_id');
            $table->string('whatsapp_waba_id', 30)->nullable()->after('meta_test_event_code');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_trackings', function (Blueprint $table) {
            $table->dropColumn(['ga4_property_id', 'search_console_site', 'whatsapp_waba_id']);
        });
    }
};

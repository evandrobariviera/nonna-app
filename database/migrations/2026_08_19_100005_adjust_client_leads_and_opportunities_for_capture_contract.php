<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Ajuste no mesmo dia da criação das tabelas (nenhum dado real ainda) pra bater
// com o contrato publicado do endpoint POST /api/leads/captura:
// - client_leads.client_id precisa ser nullable (resposta 202 = sem match de
//   fonte, lead entra pra triagem manual sem Cliente vinculado ainda)
// - client_lead_opportunities ganha os campos de atribuição/click-id que o n8n
//   manda em toda conversão (fbclid, gclid, ctwa_clid, event_id de dedup do
//   Meta CAPI, form_name, received_at) e lead_channel_id — canal resolvido
//   sempre a partir de source_channel, independente de existir client_lead_source
//   (que só existe quando o mapeamento de Cliente deu certo).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement('ALTER TABLE client_leads ALTER COLUMN client_id DROP NOT NULL');

        Schema::connection('pgsql')->table('client_leads', function (Blueprint $table) {
            $table->foreignUuid('first_seen_channel_id')->nullable()->after('client_id')
                ->constrained('lead_channels')->nullOnDelete();
        });

        Schema::connection('pgsql')->table('client_lead_opportunities', function (Blueprint $table) {
            $table->foreignUuid('lead_channel_id')->nullable()->after('client_lead_source_id')
                ->constrained('lead_channels')->nullOnDelete();
            $table->string('fbclid', 255)->nullable();
            $table->string('gclid', 255)->nullable();
            $table->string('ctwa_clid', 255)->nullable();
            $table->string('event_id', 150)->nullable(); // dedup Pixel × Conversions API (Meta CAPI)
            $table->string('form_name', 150)->nullable();
            $table->timestamp('received_at')->nullable(); // quando a conversão aconteceu de verdade (pode diferir de created_at)
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_lead_opportunities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_channel_id');
            $table->dropColumn(['fbclid', 'gclid', 'ctwa_clid', 'event_id', 'form_name', 'received_at']);
        });

        Schema::connection('pgsql')->table('client_leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('first_seen_channel_id');
        });

        DB::connection('pgsql')->statement('ALTER TABLE client_leads ALTER COLUMN client_id SET NOT NULL');
    }
};

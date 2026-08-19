<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// - client_lead_opportunities.notes vira a thread client_lead_opportunity_notes
//   (nunca foi usado em produção ainda, sem dado real pra migrar).
// - client_modules ganha requested_at: quando o Cliente clica "Quero
//   contratar" na vitrine do Portal, sem ativar nada sozinho (decisão de
//   venda continua manual do lado Nonna) — só fica registrado quando pediu.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('client_lead_opportunities', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::connection('pgsql')->table('client_modules', function (Blueprint $table) {
            $table->timestamp('requested_at')->nullable()->after('enabled_by');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_modules', function (Blueprint $table) {
            $table->dropColumn('requested_at');
        });

        Schema::connection('pgsql')->table('client_lead_opportunities', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });
    }
};

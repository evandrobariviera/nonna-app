<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esteira de onboarding (cliente ganho → cadastro → contrato):
 *  - contracts.document_url: link do contrato gerado no Google Docs (devolvido
 *    pelo n8n via /api/onboarding/{client}/step).
 *  - remove a automação "Oportunidade Ganha → Avisar grupo (n8n)": o disparo
 *    pro n8n passou a sair direto do ClientOnboardingService (evento
 *    `opportunity_won`), não mais de uma automação genérica. As notificações
 *    internas de ganho/perdido (send_notification) continuam.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('contracts', function (Blueprint $table) {
            $table->string('document_url', 500)->nullable()->after('notes');
        });

        DB::connection('pgsql')->table('automations')
            ->where('name', 'Oportunidade Ganha → Avisar grupo (n8n)')
            ->delete();
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('contracts', function (Blueprint $table) {
            $table->dropColumn('document_url');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Responsável pode ser alguém do time do cliente (Contact com Portal) OU
        // da Nonna (assigned_to → users, que já existia) — nunca os dois.
        Schema::connection('pgsql')->table('client_lead_opportunities', function (Blueprint $table) {
            $table->foreignUuid('assigned_contact_id')->nullable()->after('assigned_to')
                ->constrained('contacts')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_contact_id');
            $table->decimal('deal_value', 12, 2)->nullable()->after('stage');

            $table->index('assigned_contact_id');
            $table->index('assigned_to');
        });

        // Configurações por módulo contratado (ex: central_leads.hide_assigned).
        Schema::connection('pgsql')->table('client_modules', function (Blueprint $table) {
            $table->jsonb('settings')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_lead_opportunities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_contact_id');
            $table->dropIndex(['assigned_to']);
            $table->dropColumn(['assigned_at', 'deal_value']);
        });

        Schema::connection('pgsql')->table('client_modules', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};

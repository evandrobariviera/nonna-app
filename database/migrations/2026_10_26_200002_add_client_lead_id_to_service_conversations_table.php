<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A pessoa da Central de Leads por trás da conversa do Atendimento Assistido —
// até aqui eram duas representações soltas da mesma pessoa.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('service_conversations', function (Blueprint $table) {
            $table->foreignUuid('client_lead_id')->nullable()->after('client_integration_id')
                ->constrained('client_leads')->nullOnDelete();
            $table->index('client_lead_id');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('service_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_lead_id');
        });
    }
};

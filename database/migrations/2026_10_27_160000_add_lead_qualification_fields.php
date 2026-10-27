<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Régua de "lead bom" por cliente (IA rascunha, estrategista revisa — só
        // depois de revisada a IA passa a avaliar os leads) + ticket médio (ROI estimado).
        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            $table->decimal('average_ticket', 12, 2)->nullable();
            $table->text('lead_rule')->nullable();
            $table->timestamp('lead_rule_reviewed_at')->nullable();
            $table->foreignId('lead_rule_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Avaliação da IA (sugestão) separada da confirmação de quem atende — onde
        // há as duas, dá pra medir o acerto da IA. O desfecho confirmado é o próprio
        // estágio (ganho/perdido); ai_outcome é só a sugestão.
        Schema::connection('pgsql')->table('client_lead_opportunities', function (Blueprint $table) {
            $table->string('ai_quality', 20)->nullable();
            $table->string('ai_outcome', 20)->nullable();
            $table->decimal('ai_outcome_value', 12, 2)->nullable();
            $table->text('ai_summary')->nullable();
            $table->text('ai_reason')->nullable();
            $table->timestamp('ai_evaluated_at')->nullable();
            $table->string('quality', 20)->nullable();
            $table->timestamp('quality_confirmed_at')->nullable();
            $table->foreignId('quality_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('quality_confirmed_contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            $table->index('ai_quality');
            $table->index('quality');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_lead_opportunities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quality_confirmed_by');
            $table->dropConstrainedForeignId('quality_confirmed_contact_id');
            $table->dropIndex(['ai_quality']);
            $table->dropIndex(['quality']);
            $table->dropColumn(['ai_quality', 'ai_outcome', 'ai_outcome_value', 'ai_summary', 'ai_reason', 'ai_evaluated_at', 'quality', 'quality_confirmed_at']);
        });

        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_rule_reviewed_by');
            $table->dropColumn(['average_ticket', 'lead_rule', 'lead_rule_reviewed_at']);
        });
    }
};

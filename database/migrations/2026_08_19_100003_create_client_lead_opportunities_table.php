<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('client_lead_opportunities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_lead_id')->constrained('client_leads')->cascadeOnDelete();
            $table->foreignUuid('client_lead_source_id')->nullable()->constrained('client_lead_sources')->nullOnDelete();

            $table->enum('stage', [
                'novo', 'em_contato', 'qualificado', 'proposta_enviada', 'negociando', 'ganho', 'perdido',
            ])->default('novo');

            $table->string('utm_source', 150)->nullable();
            $table->string('utm_medium', 150)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->text('landing_page_url')->nullable();

            $table->jsonb('raw_payload')->nullable(); // payload original recebido do n8n — auditoria/debug
            $table->text('notes')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['client_lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('client_lead_opportunities');
    }
};

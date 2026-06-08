<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_onboardings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->enum('current_phase', ['fase1', 'fase2', 'fase3', 'fase4', 'fase5', 'concluido'])->default('fase1');

            // Fase 1 — Gatilho (Dia 0)
            $table->jsonb('fase1_checklist')->nullable();
            $table->text('fase1_notes')->nullable();
            $table->timestamp('fase1_completed_at')->nullable();

            // Fase 2 — Welcome Call (Dias 1-2)
            $table->jsonb('fase2_checklist')->nullable();
            $table->text('fase2_notes')->nullable();
            $table->timestamp('fase2_completed_at')->nullable();

            // Fase 3 — Setup Técnico (Dias 3-4)
            $table->jsonb('fase3_checklist')->nullable();
            $table->text('fase3_notes')->nullable();
            $table->timestamp('fase3_completed_at')->nullable();

            // Fase 4 — Kick-off Estratégico (Dia 5)
            $table->jsonb('fase4_checklist')->nullable();
            $table->text('fase4_notes')->nullable();
            $table->timestamp('fase4_completed_at')->nullable();

            // Fase 5 — Macroplanejamento e Sprint
            $table->jsonb('fase5_checklist')->nullable();
            $table->text('fase5_notes')->nullable();
            $table->timestamp('fase5_completed_at')->nullable();

            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_onboardings');
    }
};

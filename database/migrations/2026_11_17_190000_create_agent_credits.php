<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Créditos do Agente Nonna (2026-11-17): cada pessoa tem um saldo semanal (renova na
 * segunda) pra usar o chat do agente. Só o chat conta — automações com IA ficam fora.
 * 1 dólar de custo = 1.000 créditos (AgentCredits::PER_USD); o time vê só créditos.
 *
 * agent_credit_limits: limite semanal próprio de uma pessoa (sem linha = padrão).
 * agent_credit_grants: crédito extra liberado por um admin numa semana específica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('agent_credit_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('weekly_credits');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::connection('pgsql')->create('agent_credit_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedInteger('credits');
            $table->string('note', 255)->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('agent_credit_grants');
        Schema::connection('pgsql')->dropIfExists('agent_credit_limits');
    }
};

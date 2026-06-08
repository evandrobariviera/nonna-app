<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Correlação com ClickUp (preenchido pelo n8n após sync)
            $table->string('clickup_task_id')->nullable()->unique();

            // Dados da empresa
            $table->string('company_name');
            $table->string('trade_name')->nullable();
            $table->string('cnpj', 20)->nullable();
            $table->string('website')->nullable();
            $table->string('segment')->nullable();

            // Status operacional
            $table->string('status')->default('lead'); // lead | active | inactive

            // Dados financeiros e estruturais
            $table->string('monthly_revenue_range')->nullable();
            $table->string('employee_count')->nullable();
            $table->string('monthly_ad_budget')->nullable();

            // Serviços contratados (array JSON)
            $table->json('contracted_services')->nullable();

            // Contato principal (provisório até a tabela contacts ser construída)
            $table->string('primary_contact_name')->nullable();
            $table->string('primary_contact_email')->nullable();
            $table->string('primary_contact_phone')->nullable();

            // Cadastro público via link enviado ao cliente
            $table->string('registration_token', 64)->nullable()->unique();
            $table->timestamp('registration_completed_at')->nullable();

            // Notas internas
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('clients');
    }
};

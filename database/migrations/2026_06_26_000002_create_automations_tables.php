<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();

            // Em qual entidade esta automação opera
            $table->string('entity_type'); // task | project | campaign

            // Quando dispara
            $table->string('trigger_type'); // status_changed | field_updated | created | manual
            $table->jsonb('trigger_config')->default('{}');
            // Exemplos:
            // status_changed: { "field": "status", "from": "em_copy", "to": "revisao" }
            // field_updated:  { "field": "situation" }
            // created:        {}
            // manual:         {}

            // O que faz
            $table->string('action_type'); // run_ai_agent | send_webhook | update_field | send_notification
            $table->jsonb('action_config')->default('{}');
            // Exemplos:
            // run_ai_agent:      { "agent_id": "uuid" }
            // send_webhook:      { "url": "...", "method": "POST" }
            // update_field:      { "field": "status", "value": "revisao" }
            // send_notification: { "to": "executor", "message": "..." }

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('automation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->constrained('automations')->cascadeOnDelete();

            $table->string('entity_type');
            $table->uuid('entity_id');

            $table->string('status'); // running | success | failed
            $table->jsonb('input_snapshot')->nullable(); // contexto que foi enviado
            $table->text('output')->nullable();          // resposta da IA ou descrição da ação
            $table->text('error_message')->nullable();
            $table->integer('duration_ms')->nullable();

            $table->timestamp('ran_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('automation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('automations');
    }
};

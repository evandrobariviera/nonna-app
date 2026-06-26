<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $db = Schema::connection('pgsql');

        // Provedores de IA (OpenAI, Anthropic, Google, Groq…)
        if (!$db->hasTable('ai_providers')) {
            $db->create('ai_providers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('base_url');
                $table->jsonb('models')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Chaves de API por provider
        if (!$db->hasTable('ai_api_keys')) {
            $db->create('ai_api_keys', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('provider_id')->constrained('ai_providers')->onDelete('cascade');
                $table->string('label');
                $table->text('api_key_encrypted');
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // Agentes configuráveis
        if (!$db->hasTable('ai_agents')) {
            $db->create('ai_agents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('description')->nullable();
                $table->text('system_prompt');
                $table->foreignUuid('provider_id')->constrained('ai_providers')->onDelete('restrict');
                $table->foreignUuid('api_key_id')->nullable()->constrained('ai_api_keys')->nullOnDelete();
                $table->string('model');
                $table->decimal('temperature', 3, 2)->default(0.70);
                $table->unsignedInteger('max_tokens')->default(4096);
                $table->string('context_scope')->default('global');
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // Log de uso — cada chamada registrada para controle financeiro
        if (!$db->hasTable('ai_token_usage')) {
            $db->create('ai_token_usage', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->uuid('client_id')->nullable();
                $table->string('model');
                $table->string('provider');
                $table->unsignedInteger('prompt_tokens')->default(0);
                $table->unsignedInteger('completion_tokens')->default(0);
                $table->unsignedInteger('total_tokens')->default(0);
                $table->decimal('estimated_cost_usd', 10, 6)->default(0);
                $table->string('trigger')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('ai_token_usage');
        Schema::connection('pgsql')->dropIfExists('ai_agents');
        Schema::connection('pgsql')->dropIfExists('ai_api_keys');
        Schema::connection('pgsql')->dropIfExists('ai_providers');
    }
};

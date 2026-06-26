<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        // Provedores de IA (OpenAI, Anthropic, Google, Groq…)
        Schema::connection('pgsql')->create('ai_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');                          // OpenAI, Anthropic…
            $table->string('slug')->unique();                // openai, anthropic, google, groq
            $table->string('base_url');                      // https://api.openai.com/v1
            $table->jsonb('models')->nullable();             // [{id, label, input_per_1k, output_per_1k}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Chaves de API por provider (múltiplas por provider, p/ separar ambientes/uso)
        Schema::connection('pgsql')->create('ai_api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('ai_providers')->onDelete('cascade');
            $table->string('label');                         // "Chave Principal", "Dev"…
            $table->text('api_key_encrypted');               // encrypt() do Laravel
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // Agentes configuráveis
        Schema::connection('pgsql')->create('ai_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('description')->nullable();
            $table->text('system_prompt');
            $table->foreignUuid('provider_id')->constrained('ai_providers')->onDelete('restrict');
            $table->foreignUuid('api_key_id')->nullable()->constrained('ai_api_keys')->nullOnDelete();
            $table->string('model');                         // gpt-4o-mini, claude-haiku-4-5…
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->unsignedInteger('max_tokens')->default(4096);
            $table->string('context_scope')->default('global'); // global | client
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // Log de uso — cada chamada registrada para controle financeiro
        Schema::connection('pgsql')->create('ai_token_usage', function (Blueprint $table) {
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
            $table->string('trigger')->nullable();           // kickoff_extraction | dossier_fill | chat | manual
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('ai_token_usage');
        Schema::connection('pgsql')->dropIfExists('ai_agents');
        Schema::connection('pgsql')->dropIfExists('ai_api_keys');
        Schema::connection('pgsql')->dropIfExists('ai_providers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignUuid('macro_plan_id')->nullable()->constrained('macro_plans')->nullOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();

            $table->string('title', 300);
            $table->text('description')->nullable();

            // Tipo e disciplina
            $table->string('task_type', 30)->default('criacao');
            // criacao | web | trafego | setup | social | seo | email | estrategia | administrativo

            // Status workflow
            $table->string('status', 30)->default('backlog');
            // backlog | em_copy | pronto_producao | em_producao | revisao
            // aguardando_envio | aguardando_resposta | concluido | ajuste | cancelado

            $table->string('situation', 150)->nullable(); // sub-status livre

            // Pessoas
            $table->foreignId('executor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');

            // Datas
            $table->date('due_date')->nullable();
            $table->date('approval_date')->nullable();
            $table->date('publish_date')->nullable();

            // Aprovação
            $table->string('approval_location', 100)->nullable(); // ClickUp, WhatsApp, e-mail...

            // Origem e tipo especial
            $table->string('origin', 20)->default('projeto'); // projeto | onboarding | ticket
            $table->boolean('is_ticket')->default(false);

            // ClickUp
            $table->string('clickup_task_id', 100)->nullable();
            $table->timestamp('launched_at')->nullable();

            $table->jsonb('custom_fields')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('tasks');
    }
};

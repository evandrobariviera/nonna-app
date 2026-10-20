<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Pendência: algo que alguém precisa ENTREGAR pra o trabalho andar (insumo, acesso,
    // agendamento, decisão...) — não é trabalho nosso (isso é Tarefa). Sempre tem 2 pessoas:
    // quem deve (cliente/agência/terceiro) e quem cobra (owner_id). Pode nascer numa Tarefa/
    // Ticket, Projeto ou Planejamento (pendable_*) ou avulsa, só no Cliente.
    //
    // Substitui os "Itens de ação" (task_checklist_items) — copiados aqui como pendências
    // "deve: agência". A tabela antiga fica intocada por enquanto (rede de segurança),
    // só deixa de ser usada pelo código.
    public function up(): void
    {
        Schema::connection('pgsql')->create('pendencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->foreignUuid('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pendable_type')->nullable();
            $table->uuid('pendable_id')->nullable();
            $table->foreignUuid('source_comment_id')->nullable()->constrained('task_comments')->nullOnDelete();

            $table->text('title');
            $table->text('description')->nullable();
            $table->string('kind', 30)->default('outro');

            $table->string('owed_by', 20)->default('cliente'); // cliente | agencia | terceiro
            $table->foreignUuid('owed_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('owed_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('owed_party')->nullable(); // terceiro: nome livre (gráfica, fornecedor...)

            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(); // quem cobra
            $table->date('due_date')->nullable();

            $table->string('status', 20)->default('aberta'); // aberta | cobrada | resolvida | cancelada
            $table->unsignedInteger('nudge_count')->default(0);
            $table->timestamp('last_nudged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pendable_type', 'pendable_id']);
            $table->index(['organization_id', 'status', 'owed_by']);
            $table->index(['owner_id', 'status']);
        });

        // Itens de ação → pendências "deve: agência". Feito → resolvida (com a data em que
        // foi marcado). Quem criou o item vira quem cobra.
        DB::connection('pgsql')->statement(<<<'SQL'
            INSERT INTO pendencies (
                id, organization_id, client_id, pendable_type, pendable_id, source_comment_id,
                title, kind, owed_by, owed_user_id, owner_id, status, resolved_at, resolved_by,
                created_by, created_at, updated_at
            )
            SELECT
                i.id, t.organization_id, t.client_id, 'task', i.task_id, i.source_comment_id,
                i.title, 'outro', 'agencia', i.assigned_to, COALESCE(i.created_by, i.assigned_to),
                CASE WHEN i.done THEN 'resolvida' ELSE 'aberta' END,
                CASE WHEN i.done THEN COALESCE(i.done_at, i.updated_at) END,
                CASE WHEN i.done THEN i.assigned_to END,
                i.created_by, i.created_at, i.updated_at
            FROM task_checklist_items i
            JOIN tasks t ON t.id = i.task_id
            WHERE t.organization_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('pendencies');
    }
};

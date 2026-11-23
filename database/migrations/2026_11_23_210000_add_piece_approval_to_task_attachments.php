<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aprovação por peça (23/11): pacote com dezenas de cards não volta inteiro
        // por causa de um ajuste. Cada entregável guarda a própria situação:
        //   pending            → com o cliente (rodada aberta)
        //   approved           → aprovada, travada, pode baixar
        //   changes_requested  → cliente pediu ajuste nesta peça
        //   superseded         → versão anterior (foi substituída por outra)
        // replaces_id liga a versão nova à anterior ("Substituir"); version = v1, v2...
        $db = Schema::connection('pgsql');
        $db->table('task_attachments', function (Blueprint $table) use ($db) {
            if (!$db->hasColumn('task_attachments', 'approval_status')) {
                $table->string('approval_status', 30)->nullable();
            }
            if (!$db->hasColumn('task_attachments', 'replaces_id')) {
                $table->uuid('replaces_id')->nullable();
            }
            if (!$db->hasColumn('task_attachments', 'version')) {
                $table->unsignedSmallInteger('version')->default(1);
            }
        });

        // Histórico: a situação de cada peça já enviada é a da rodada em que ela foi.
        DB::connection('pgsql')->statement("
            UPDATE task_attachments a
               SET approval_status = r.status
              FROM task_approval_rounds r
             WHERE a.is_deliverable = true
               AND a.approval_status IS NULL
               AND r.task_id = a.task_id
               AND r.round_number = a.round_number
               AND r.status IN ('pending', 'approved', 'changes_requested')
        ");

        // Ajuste antigo que já teve rodada depois = foi refeito (antes não havia
        // "Substituir"; a equipe subia arquivos novos) → versão anterior. Só conta
        // rodada com arquivo novo (Aviso não substitui nada) — ver 2026_11_23_220000.
        DB::connection('pgsql')->statement("
            UPDATE task_attachments a
               SET approval_status = 'superseded'
             WHERE a.approval_status = 'changes_requested'
               AND EXISTS (
                   SELECT 1 FROM task_approval_rounds r
                    WHERE r.task_id = a.task_id
                      AND r.round_number > a.round_number
                      AND r.status <> 'cancelled'
               )
               AND EXISTS (
                   SELECT 1
                     FROM task_attachments b
                     JOIN task_approval_rounds r0 ON r0.task_id = a.task_id AND r0.round_number = a.round_number
                    WHERE b.task_id = a.task_id
                      AND b.kind = 'entregavel'
                      AND b.created_at > COALESCE(r0.resolved_at, r0.submitted_at)
               )
        ");
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_attachments', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'replaces_id', 'version']);
        });
    }
};

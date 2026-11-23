<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Correção do backfill de 2026_11_23_210000: ele marcou "versão anterior" toda
        // peça com ajuste que tinha rodada depois — inclusive quando a rodada seguinte
        // foi um Aviso, sem arquivo novo nenhum (achado real: entregáveis "sumiram" de
        // 8 tarefas). Só é versão anterior se chegou entregável DEPOIS do ajuste; nos
        // outros casos a peça volta neutra (enviada, sem situação).
        DB::connection('pgsql')->statement("
            UPDATE task_attachments a
               SET approval_status = NULL
             WHERE a.approval_status = 'superseded'
               AND a.replaces_id IS NULL
               AND NOT EXISTS (SELECT 1 FROM task_attachments n WHERE n.replaces_id = a.id)
               AND NOT EXISTS (
                   SELECT 1
                     FROM task_attachments b
                     JOIN task_approval_rounds r ON r.task_id = a.task_id AND r.round_number = a.round_number
                    WHERE b.task_id = a.task_id
                      AND b.kind = 'entregavel'
                      AND b.created_at > COALESCE(r.resolved_at, r.submitted_at)
               )
        ");
    }

    public function down(): void
    {
        // Sem volta: o estado anterior era o erro.
    }
};

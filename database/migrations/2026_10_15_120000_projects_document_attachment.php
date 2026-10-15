<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documento da campanha/projeto num campo próprio — mesma dinâmica do planejamento
 * (2026_10_15_110000): só o HTML enviado como documento aparece na página e é lido pelo
 * Assistente de Tarefas; anexos diversos não trocam mais o embed.
 *
 * Existentes: marca o HTML mais recente (o que a página já mostrava).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->uuid('document_attachment_id')->nullable();
            $table->foreign('document_attachment_id')->references('id')->on('project_attachments')->nullOnDelete();
        });

        DB::statement("
            UPDATE projects p SET document_attachment_id = (
                SELECT a.id FROM project_attachments a
                WHERE a.project_id = p.id AND a.mime_type ILIKE '%html%'
                ORDER BY a.created_at DESC LIMIT 1
            )
            WHERE EXISTS (
                SELECT 1 FROM project_attachments a
                WHERE a.project_id = p.id AND a.mime_type ILIKE '%html%'
            )
        ");
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['document_attachment_id']);
            $table->dropColumn('document_attachment_id');
        });
    }
};

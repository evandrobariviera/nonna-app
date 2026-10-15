<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documento do planejamento num campo próprio (pedido do Evandro em 2026-10-15): antes a
 * página exibia "o HTML mais recente dos anexos", e qualquer HTML anexado à toa virava o
 * documento. Agora só o enviado como documento (MacroPlanDocumentService::setDocument).
 *
 * Pros planejamentos que já existem, marca como documento exatamente o que a página já
 * mostrava (HTML mais recente) — ninguém percebe diferença na virada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('macro_plans', function (Blueprint $table) {
            $table->uuid('document_attachment_id')->nullable()->after('focus');
            $table->foreign('document_attachment_id')->references('id')->on('macro_plan_attachments')->nullOnDelete();
        });

        DB::statement("
            UPDATE macro_plans mp SET document_attachment_id = (
                SELECT a.id FROM macro_plan_attachments a
                WHERE a.macro_plan_id = mp.id AND a.mime_type ILIKE '%html%'
                ORDER BY a.created_at DESC LIMIT 1
            )
        ");
    }

    public function down(): void
    {
        Schema::table('macro_plans', function (Blueprint $table) {
            $table->dropForeign(['document_attachment_id']);
            $table->dropColumn('document_attachment_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Entregáveis (23/11): o time arrasta pra definir a ordem que o cliente vê na
        // aprovação (sort_order; vazio = ordem de envio) e a tela mostra uma miniatura
        // leve gerada no upload (thumb_path, no mesmo disco do arquivo).
        $db = Schema::connection('pgsql');
        $db->table('task_attachments', function (Blueprint $table) use ($db) {
            if (!$db->hasColumn('task_attachments', 'sort_order')) {
                $table->integer('sort_order')->nullable();
            }
            if (!$db->hasColumn('task_attachments', 'thumb_path')) {
                $table->string('thumb_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_attachments', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'thumb_path']);
        });
    }
};

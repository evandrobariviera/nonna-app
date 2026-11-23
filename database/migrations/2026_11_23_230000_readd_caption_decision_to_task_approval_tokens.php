<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajuste só na legenda (aprovação por peça, 23/11) volta a usar a decisão da
        // legenda por aprovador — as colunas tinham sido criadas em 2026_07_30_000010 e
        // removidas em 2026_07_30_000011. Sem elas, gravar a decisão do cliente quebrava.
        $db = Schema::connection('pgsql');
        $db->table('task_approval_tokens', function (Blueprint $table) use ($db) {
            if (!$db->hasColumn('task_approval_tokens', 'caption_status')) {
                $table->string('caption_status', 30)->nullable();
            }
            if (!$db->hasColumn('task_approval_tokens', 'caption_comment')) {
                $table->text('caption_comment')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_approval_tokens', function (Blueprint $table) {
            $table->dropColumn(['caption_status', 'caption_comment']);
        });
    }
};

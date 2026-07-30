<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // Revertido no mesmo dia: a decisão do cliente é pra rodada inteira (material +
    // legenda juntos), não por peça — granularidade por arquivo/legenda fica pra quem
    // quiser, resolvida separando em tarefas distintas. Ver TaskApprovalService::submitDecision().
    public function up(): void
    {
        Schema::connection('pgsql')->table('task_approval_tokens', function (Blueprint $table) {
            $table->dropColumn(['caption_status', 'caption_comment']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_approval_tokens', function (Blueprint $table) {
            $table->string('caption_status', 30)->nullable()->after('overall_comment');
            $table->text('caption_comment')->nullable()->after('caption_status');
        });
    }
};

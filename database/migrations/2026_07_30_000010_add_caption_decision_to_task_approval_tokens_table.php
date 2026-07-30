<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('task_approval_tokens', function (Blueprint $table) {
            // Decisão desse aprovador sobre a legenda, separada da decisão por
            // arquivo (deliverable_feedbacks) — null enquanto não houver legenda
            // pra avaliar ou o aprovador ainda não decidiu.
            $table->string('caption_status', 30)->nullable()->after('overall_comment');
            $table->text('caption_comment')->nullable()->after('caption_status');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_approval_tokens', function (Blueprint $table) {
            $table->dropColumn(['caption_status', 'caption_comment']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('task_approval_rounds', function (Blueprint $table) {
            $table->foreignId('portal_decided_by')->nullable()->after('sent_at')->constrained('users')->nullOnDelete();
            $table->string('portal_decision', 30)->nullable()->after('portal_decided_by');
            $table->text('portal_comment')->nullable()->after('portal_decision');
            $table->timestamp('portal_decided_at')->nullable()->after('portal_comment');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_approval_rounds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('portal_decided_by');
            $table->dropColumn(['portal_decision', 'portal_comment', 'portal_decided_at']);
        });
    }
};

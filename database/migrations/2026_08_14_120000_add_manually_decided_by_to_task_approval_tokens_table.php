<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_approval_tokens', function (Blueprint $table) {
            $table->foreignId('manually_decided_by')->nullable()->after('will_notify')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_approval_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manually_decided_by');
        });
    }
};

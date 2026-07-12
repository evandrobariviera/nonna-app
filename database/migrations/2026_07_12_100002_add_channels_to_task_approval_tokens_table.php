<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('task_approval_tokens', function (Blueprint $table) {
            $table->jsonb('channels')->nullable()->after('contact_id');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_approval_tokens', function (Blueprint $table) {
            $table->dropColumn('channels');
        });
    }
};

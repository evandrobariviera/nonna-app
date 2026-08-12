<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_approval_tokens', function (Blueprint $table) {
            $table->boolean('will_notify')->default(true)->after('channels');
        });
    }

    public function down(): void
    {
        Schema::table('task_approval_tokens', function (Blueprint $table) {
            $table->dropColumn('will_notify');
        });
    }
};

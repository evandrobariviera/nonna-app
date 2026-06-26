<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::table('automation_logs', function (Blueprint $table) {
            $table->foreignUuid('automation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('automation_logs', function (Blueprint $table) {
            $table->foreignUuid('automation_id')->nullable(false)->change();
        });
    }
};

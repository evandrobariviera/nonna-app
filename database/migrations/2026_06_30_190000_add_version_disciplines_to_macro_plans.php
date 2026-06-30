<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('macro_plans', function (Blueprint $table) {
            $table->string('version', 20)->default('1.0')->after('launched_at');
            $table->jsonb('disciplines')->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('macro_plans', function (Blueprint $table) {
            $table->dropColumn(['version', 'disciplines']);
        });
    }
};

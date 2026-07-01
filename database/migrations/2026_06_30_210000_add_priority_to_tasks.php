<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->string('priority', 20)->default('normal')->after('status');
            // urgente | medio | normal
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};

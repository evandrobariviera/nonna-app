<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->string('clickup_list_id', 100)->nullable()->after('clickup_task_id');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->dropColumn('clickup_list_id');
        });
    }
};

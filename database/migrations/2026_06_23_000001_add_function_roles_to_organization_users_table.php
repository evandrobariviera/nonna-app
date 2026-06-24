<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('organization_users', function (Blueprint $table) {
            $table->jsonb('function_roles')->default('[]')->after('role');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('organization_users', function (Blueprint $table) {
            $table->dropColumn('function_roles');
        });
    }
};

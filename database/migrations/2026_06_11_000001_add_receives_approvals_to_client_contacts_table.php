<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('client_contacts', function (Blueprint $table) {
            $table->boolean('receives_approvals')->default(false)->after('is_primary');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_contacts', function (Blueprint $table) {
            $table->dropColumn('receives_approvals');
        });
    }
};

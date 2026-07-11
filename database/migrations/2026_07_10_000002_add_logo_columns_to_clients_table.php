<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('company_name');
            $table->string('logo_disk')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'logo_disk']);
        });
    }
};

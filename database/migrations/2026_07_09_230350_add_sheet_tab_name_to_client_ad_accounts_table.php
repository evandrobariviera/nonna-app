<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('client_ad_accounts', function (Blueprint $table) {
            $table->string('sheet_tab_name')->nullable()->after('account_name');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_ad_accounts', function (Blueprint $table) {
            $table->dropColumn('sheet_tab_name');
        });
    }
};

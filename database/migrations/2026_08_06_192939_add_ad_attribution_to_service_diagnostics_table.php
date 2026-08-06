<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('service_diagnostics', function (Blueprint $table) {
            $table->jsonb('ad_attribution')->nullable()->after('campaign_insights');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('service_diagnostics', function (Blueprint $table) {
            $table->dropColumn('ad_attribution');
        });
    }
};

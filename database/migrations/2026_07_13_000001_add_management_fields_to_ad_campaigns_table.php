<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('ad_campaigns', function (Blueprint $table) {
            $table->string('management_status')->default('em_veiculacao')->after('status');
            $table->string('management_situation')->nullable()->after('management_status');
            $table->string('optimization_tier')->default('normal')->after('management_situation');
            $table->timestamp('last_optimized_at')->nullable()->after('optimization_tier');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn(['management_status', 'management_situation', 'optimization_tier', 'last_optimized_at']);
        });
    }
};

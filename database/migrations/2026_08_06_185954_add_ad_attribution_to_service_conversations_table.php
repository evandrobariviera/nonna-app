<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('service_conversations', function (Blueprint $table) {
            $table->string('ad_source_id', 100)->nullable()->after('is_group');
            $table->string('ad_title', 255)->nullable()->after('ad_source_id');
            $table->text('ad_ctwa_clid')->nullable()->after('ad_title');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('service_conversations', function (Blueprint $table) {
            $table->dropColumn(['ad_source_id', 'ad_title', 'ad_ctwa_clid']);
        });
    }
};

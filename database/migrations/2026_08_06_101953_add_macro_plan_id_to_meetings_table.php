<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('meetings', function (Blueprint $table) {
            $table->foreignUuid('macro_plan_id')->nullable()->after('opportunity_id')
                ->constrained('macro_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('macro_plan_id');
        });
    }
};

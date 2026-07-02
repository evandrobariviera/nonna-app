<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('macro_plans', function (Blueprint $table) {
            $table->jsonb('clickup_attachments')->nullable()->after('launched_at');
        });

        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->jsonb('clickup_attachments')->nullable()->after('launched_at');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('macro_plans', function (Blueprint $table) {
            $table->dropColumn('clickup_attachments');
        });

        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->dropColumn('clickup_attachments');
        });
    }
};

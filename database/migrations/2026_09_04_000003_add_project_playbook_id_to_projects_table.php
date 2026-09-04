<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->foreignUuid('project_playbook_id')->nullable()
                ->after('macro_plan_id')
                ->constrained('project_playbooks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_playbook_id');
        });
    }
};

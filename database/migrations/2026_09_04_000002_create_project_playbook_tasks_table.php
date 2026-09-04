<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('project_playbook_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_playbook_id')->constrained('project_playbooks')->cascadeOnDelete();

            $table->unsignedSmallInteger('position')->default(1);
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->string('task_type', 30);                // Task::$types
            $table->string('destination', 40)->nullable();   // Task::$destinations
            $table->string('priority', 10)->nullable();      // Task::$priorities
            $table->unsignedSmallInteger('due_offset_days')->nullable();
            $table->foreignUuid('functional_role_id')->nullable()->constrained('functional_roles')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_playbook_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('project_playbook_tasks');
    }
};

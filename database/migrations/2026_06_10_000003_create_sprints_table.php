<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('sprints', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('title', 150);
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('status', 20)->default('planning');
            // planning | active | closed

            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // Adiciona sprint_id às tasks
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->foreignUuid('sprint_id')
                ->nullable()
                ->after('project_id')
                ->constrained('sprints')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->dropForeign(['sprint_id']);
            $table->dropColumn('sprint_id');
        });

        Schema::connection('pgsql')->dropIfExists('sprints');
    }
};

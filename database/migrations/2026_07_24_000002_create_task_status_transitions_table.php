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
        Schema::connection('pgsql')->create('task_status_transitions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable(); // null = transição de criação da tarefa
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['task_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('task_status_transitions');
    }
};

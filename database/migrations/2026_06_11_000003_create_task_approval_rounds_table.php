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
        Schema::connection('pgsql')->create('task_approval_rounds', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number')->default(1);
            $table->foreignId('submitted_by')->constrained('users');
            $table->timestamp('submitted_at');
            // pending | approved | changes_requested | cancelled
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('task_approval_rounds');
    }
};

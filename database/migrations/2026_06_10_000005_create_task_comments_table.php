<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('task_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
        });

        DB::connection('pgsql')->statement(
            'ALTER TABLE task_comments ALTER COLUMN id SET DEFAULT gen_random_uuid()'
        );
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('task_comments');
    }
};

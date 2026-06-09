<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_personas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('diagnostic_id')->constrained('client_diagnostics')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('name', 100);
            $table->string('age_range', 50)->nullable();
            $table->string('profession', 150)->nullable();
            $table->string('income', 100)->nullable();
            $table->string('location', 150)->nullable();
            $table->text('what_they_seek')->nullable();
            $table->text('frustrations')->nullable();
            $table->text('decision_process')->nullable();
            $table->text('objections')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_personas');
    }
};

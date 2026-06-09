<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_competitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('diagnostic_id')->constrained('client_diagnostics')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('name', 200);
            $table->string('main_channels', 200)->nullable();
            $table->text('positioning')->nullable();
            $table->text('strengths')->nullable();
            $table->text('vulnerability')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_competitors');
    }
};

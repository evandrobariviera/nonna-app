<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_diagnostics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('title', 200)->nullable();
            $table->string('status', 20)->default('draft'); // draft | complete
            $table->jsonb('sec01_briefing')->nullable();
            $table->jsonb('sec02_marketing')->nullable();
            $table->jsonb('sec03_audit')->nullable();
            $table->jsonb('sec04_competition')->nullable();
            $table->jsonb('sec05_persona')->nullable();
            $table->jsonb('sec06_synthesis')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_diagnostics');
    }
};

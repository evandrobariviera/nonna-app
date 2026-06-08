<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('job_title')->nullable();
            $table->string('company_name')->nullable();
            $table->enum('source', ['whatsapp', 'instagram', 'indicacao', 'site', 'linkedin', 'evento', 'outros'])->default('outros');
            $table->enum('status', ['novo', 'qualificado', 'em_negociacao', 'ganho', 'perdido', 'inativo'])->default('novo');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

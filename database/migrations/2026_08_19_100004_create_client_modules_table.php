<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('client_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();

            $table->string('module_key', 50);
            $table->string('status', 20)->default('nao_contratado'); // nao_contratado | ativo

            $table->timestamp('enabled_at')->nullable();
            $table->foreignId('enabled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['client_id', 'module_key']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('client_modules');
    }
};

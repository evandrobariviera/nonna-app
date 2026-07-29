<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('internal_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('kind', 50);
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->string('link')->nullable();

            // Origem opcional (ex: task/019...) — só pra referência/debug, sem FK
            // de verdade porque source_type varia entre entidades diferentes.
            $table->string('source_type', 50)->nullable();
            $table->string('source_id')->nullable();

            $table->string('status', 20)->default('novo'); // novo | lido | resolvido | descartado
            $table->timestamp('generated_at');

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('internal_notifications');
    }
};

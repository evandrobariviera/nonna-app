<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('client_leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 2)->nullable();

            $table->timestamp('first_contacted_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();

            $table->timestamps();

            $table->index(['client_id', 'phone']);
            $table->index(['client_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('client_leads');
    }
};

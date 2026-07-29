<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('sectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');

            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::connection('pgsql')->create('sector_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sector_id')->constrained('sectors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sector_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('sector_user');
        Schema::connection('pgsql')->dropIfExists('sectors');
    }
};

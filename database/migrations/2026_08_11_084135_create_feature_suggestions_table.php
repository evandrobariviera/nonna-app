<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('feature_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');

            $table->string('title', 500);
            $table->text('description')->nullable();

            $table->foreignId('created_by')->constrained('users');

            $table->boolean('done')->default(false);
            $table->timestamp('done_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('feature_suggestions');
    }
};

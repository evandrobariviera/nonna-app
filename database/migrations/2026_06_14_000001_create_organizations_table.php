<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('plan')->default('trial');    // trial | starter | pro | enterprise
            $table->string('status')->default('trial');  // active | suspended | trial | cancelled
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->jsonb('settings')->nullable();       // branding: logo_url, primary_color, secondary_color
            $table->timestamps();

            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('organizations');
    }
};

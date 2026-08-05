<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('functional_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');

            $table->string('key', 50);
            $table->string('name', 100);
            $table->boolean('is_protected')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'key']);
        });

        Schema::connection('pgsql')->create('functional_role_user', function (Blueprint $table) {
            $table->foreignUuid('functional_role_id')->constrained('functional_roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['functional_role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('functional_role_user');
        Schema::connection('pgsql')->dropIfExists('functional_roles');
    }
};

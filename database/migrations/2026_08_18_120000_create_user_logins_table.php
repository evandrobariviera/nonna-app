<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Registro de login de usuário interno (guard "web") — alimenta o Monitor de
    // Trabalho junto com task_activities. Contato do Portal já tem seu próprio
    // rastro (contacts.portal_last_login_at), não passa por aqui.
    public function up(): void
    {
        Schema::connection('pgsql')->create('user_logins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('logged_in_at')->useCurrent();

            $table->index(['user_id', 'logged_in_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('user_logins');
    }
};

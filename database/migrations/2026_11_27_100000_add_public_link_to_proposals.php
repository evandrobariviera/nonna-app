<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Página da proposta pro cliente (/proposta/{token}).
 * public_token: código do link, sem hífen (WhatsApp lê UUID com hífen como chave Pix).
 * public_views/public_last_view_at: quantas vezes o cliente abriu (equipe logada não conta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->string('public_token', 40)->nullable()->unique();
            $table->unsignedInteger('public_views')->default(0);
            $table->timestamp('public_last_view_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['public_token', 'public_views', 'public_last_view_at']);
        });
    }
};

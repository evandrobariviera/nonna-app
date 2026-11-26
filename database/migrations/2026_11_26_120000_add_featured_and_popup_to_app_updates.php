<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Janela de Novidades no primeiro acesso do dia (partials/_novidades-popup).
 *  - app_updates.is_featured: "Destaque" — aparece grande, com detalhes abertos, no topo da janela.
 *  - users.app_updates_popup_on: dia em que a janela já abriu pra essa pessoa (abre 1x por dia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_updates', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->date('app_updates_popup_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('app_updates_popup_on'));
        Schema::table('app_updates', fn (Blueprint $table) => $table->dropColumn('is_featured'));
    }
};

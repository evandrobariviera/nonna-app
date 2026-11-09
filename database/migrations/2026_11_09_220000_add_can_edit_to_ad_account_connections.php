<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas e conexões: o App pode EDITAR a conta (criar anúncio, pausar, mudar
 * verba)? Na Meta isso depende da função do usuário do sistema na conta
 * (user_tasks com ADVERTISE); em 09/11/2026, 7 contas davam só leitura.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('ad_account_connections', function (Blueprint $table) {
            $table->boolean('can_edit')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('ad_account_connections', function (Blueprint $table) {
            $table->dropColumn('can_edit');
        });
    }
};

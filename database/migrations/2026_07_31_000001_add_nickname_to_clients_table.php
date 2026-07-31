<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            // Apelido interno — como o time se refere ao cliente no dia a dia, quando
            // a razão social não é memorável. Usado como nome de exibição em quase
            // todo lugar (ver Client::displayName()); a razão social continua sendo
            // a fonte de verdade legal/fiscal (contratos, financeiro, cadastro público).
            $table->string('nickname', 150)->nullable()->after('company_name');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            $table->dropColumn('nickname');
        });
    }
};

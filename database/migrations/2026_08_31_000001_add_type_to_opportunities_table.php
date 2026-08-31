<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo da oportunidade: novo_cliente | projeto | follow_up.
 *
 * Muda o fluxo de fechamento (só "novo_cliente" cria um Client novo — os outros
 * dois fecham como ganho vinculando a um cliente já existente) e entra no
 * contexto das Automações (webhook pro grupo da agência / notificação interna),
 * porque a mensagem que a equipe manda depende de ser cliente novo, um projeto
 * extra ou uma retomada de negociação.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('opportunities', function (Blueprint $table) {
            $table->string('type', 30)->default('novo_cliente')->after('title');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('opportunities', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

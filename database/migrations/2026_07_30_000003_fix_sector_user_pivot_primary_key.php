<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A tabela `sector_user` tinha uma coluna `id` uuid sem default —
        // Eloquent::attach()/sync() não gera esse valor sozinho (não é um
        // model com HasUuids, é só uma tabela pivot simples), violando o
        // not-null. Convenção padrão do Laravel pra pivot: sem coluna id
        // própria, chave composta (sector_id, user_id) — já garantida pelo
        // unique constraint existente.
        DB::connection('pgsql')->statement('alter table sector_user drop constraint sector_user_pkey');

        Schema::connection('pgsql')->table('sector_user', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->primary(['sector_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('sector_user', function (Blueprint $table) {
            $table->dropPrimary(['sector_id', 'user_id']);
            $table->uuid('id')->first();
        });
    }
};

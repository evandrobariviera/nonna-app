<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('task_attachments', function (Blueprint $table) {
            // insumo (referência/material recebido) | entregavel (material produzido, vai pra aprovação)
            // default 'entregavel' preserva o comportamento de uploads antigos (todos elegíveis à aprovação)
            $table->string('kind', 20)->default('entregavel')->after('round_number');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_attachments', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};

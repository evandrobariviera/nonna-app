<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Trava" — aviso liga/desliga na tarefa: "enquanto isso não sai, outras pessoas ficam
// paradas" (ex: distribuir os projetos do Macro, criar o conceito da campanha). Não
// bloqueia nada, só chama atenção. null = desligada; preenchida = travando desde quando.
// Coluna nullable sem default: no Postgres é só metadado, não reescreve a tabela.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('blocker_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('blocker_at');
        });
    }
};

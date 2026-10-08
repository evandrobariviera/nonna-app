<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Primeira vez que o CONTATO abriu o link de aprovação — pro atendimento
    // saber se cobra "nem abriu" (número/e-mail errado?) ou "abriu e não
    // respondeu". Não conta pré-visualização do WhatsApp nem gente da equipe.
    public function up(): void
    {
        Schema::connection('pgsql')->table('task_approval_tokens', function (Blueprint $table) {
            $table->timestamp('first_opened_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_approval_tokens', function (Blueprint $table) {
            $table->dropColumn('first_opened_at');
        });
    }
};

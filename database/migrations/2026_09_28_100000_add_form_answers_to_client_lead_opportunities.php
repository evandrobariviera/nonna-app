<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('client_lead_opportunities', function (Blueprint $table) {
            // [{label, value}] na ordem do formulário — assunto, mensagem, perguntas do Lead Ad etc.
            $table->jsonb('form_answers')->nullable();
            // Envios anteriores que reabriram este mesmo cartão (janela de 72h), pra não perder o conteúdo.
            $table->jsonb('previous_submissions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_lead_opportunities', function (Blueprint $table) {
            $table->dropColumn(['form_answers', 'previous_submissions']);
        });
    }
};

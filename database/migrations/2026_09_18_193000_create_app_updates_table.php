<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Novidades do App — o registro do que muda no sistema, escrito pra equipe ler.
 *
 * Coisa diferente de:
 *  - notificações (internal_notifications): evento pontual dirigido a alguém;
 *  - Central de Ajuda (help_articles): como usar, sempre atual;
 *  - Sugestões (feature_suggestions): o que o time pede, entrada e não saída.
 *
 * Aqui é linha do tempo: o que mudou, quando, e por quê importa pra quem usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_updates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('summary')->nullable();  // uma linha, é o que aparece na lista
            $table->text('body')->nullable();     // detalhe em texto rico (opcional)
            $table->string('kind')->default('melhoria'); // novidade | melhoria | correcao
            $table->string('area')->nullable();   // Tarefas, Clientes, Produção...

            // Rascunho não aparece pra equipe. A data da publicação é que ordena a linha
            // do tempo (e não created_at), pra poder registrar algo entregue ontem.
            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'published_at']);
        });

        // Marca "até onde essa pessoa já leu" — é o que permite o aviso de novidade sem
        // usar o sistema de notificações, que o Evandro quis manter separado.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('app_updates_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('app_updates_seen_at'));
        Schema::dropIfExists('app_updates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Revisão de notificações (2026-11-06, "atenção eficiente"): aviso repetido do mesmo tipo
// sobre o mesmo item, ainda não aberto, deixa de virar linha nova — o existente é atualizado
// e conta quantas vezes aconteceu ("3 novos comentários"). Ver NotificationService.
// ADD COLUMN com default constante é instantâneo no Postgres (não reescreve a tabela).
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('pgsql')->hasColumn('internal_notifications', 'repeat_count')) {
            Schema::connection('pgsql')->table('internal_notifications', function (Blueprint $table) {
                $table->unsignedSmallInteger('repeat_count')->default(1);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('internal_notifications', fn (Blueprint $t) => $t->dropColumn('repeat_count'));
    }
};

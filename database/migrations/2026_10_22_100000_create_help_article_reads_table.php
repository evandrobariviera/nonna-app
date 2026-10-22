<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // Quem leu qual capítulo da Central de Ajuda, e quando — alimenta o contador do menu
    // (capítulo novo, ou atualizado depois da última leitura). Abrir o capítulo = ler.
    public function up(): void
    {
        Schema::connection('pgsql')->create('help_article_reads', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('help_article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->unique(['help_article_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('help_article_reads');
    }
};

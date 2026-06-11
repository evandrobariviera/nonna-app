<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        // ── Novos campos na tabela tasks ─────────────────────────────────────
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            // Destino da tarefa (canal/tipo de entrega)
            $table->string('destination', 40)->nullable()->after('task_type');
            // publicacao_organica | campanhas_patrocinadas | projeto_web
            // administrativo_financeiro | envio_resposta_cliente

            // Método de aprovação pelo cliente
            $table->string('approval_method', 20)->nullable()->after('approval_location');
            // aprovaaí | whatsapp | email

            // Aprovação interna antes de ir ao cliente
            $table->boolean('internal_approval')->default(false)->after('approval_method');

            // Dados do solicitante (para tickets externos)
            $table->string('requester_name', 150)->nullable()->after('internal_approval');
            $table->string('requester_whatsapp', 30)->nullable()->after('requester_name');
            $table->string('requester_channel', 20)->nullable()->after('requester_whatsapp');
            // whatsapp | email | presencial
        });

        // ── Tabela task_executors (múltiplos executores/responsáveis) ────────
        Schema::connection('pgsql')->create('task_executors', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('executor');
            // executor | responsavel | aprovador

            $table->unique(['task_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('task_executors');

        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'destination',
                'approval_method',
                'internal_approval',
                'requester_name',
                'requester_whatsapp',
                'requester_channel',
            ]);
        });
    }
};

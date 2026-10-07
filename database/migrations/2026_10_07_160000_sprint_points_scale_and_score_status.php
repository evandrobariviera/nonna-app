<?php

use App\Services\Tasks\SprintPoints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pontos de sprint, 2ª versão (decisões do usuário em 2026-10-07):
 *  - Cada tipo escolhe EM QUAL STATUS a tarefa pontua (task_type_points.score_status) —
 *    Criação pontua ao chegar em Revisão Interna (fim do trabalho de quem executou), o resto
 *    na conclusão. Ver SprintPoints::scoreStatuses().
 *  - Pesos novos, proporcionais entre formatos (a conta usou "1 ponto ≈ 15 min" só como
 *    estimativa — não é regra), calibrados pela análise do tempo em "Em
 *    Produção" (horas úteis) por formato e pela otimização de campanha (~12 min = 1 ponto).
 *    Landing page e site ficaram com ponto de partida (40/80) — os dados de web exageram
 *    porque a tarefa fica dias "em produção" junto com outras.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('task_type_points', function (Blueprint $table) {
            $table->string('score_status', 30)->default('concluido');
        });

        DB::connection('pgsql')->table('task_type_points')->where('task_type', 'criacao')
            ->update(['score_status' => 'revisao_interna']);

        // Pontos novos: tipos pela tabela padrão; formatos pelo nome do catálogo inicial
        // (formato criado/renomeado à mão fica como está).
        foreach (SprintPoints::DEFAULT_TYPE_POINTS as $type => $points) {
            DB::connection('pgsql')->table('task_type_points')->where('task_type', $type)->update(['points' => $points]);
        }
        foreach (SprintPoints::DEFAULT_FORMATS as $type => $formats) {
            foreach ($formats as [$name, $points]) {
                DB::connection('pgsql')->table('task_formats')
                    ->where('task_type', $type)->where('name', $name)->update(['points' => $points]);
            }
        }

        foreach (DB::connection('pgsql')->table('organizations')->pluck('id') as $orgId) {
            app(SprintPoints::class)->recalculate($orgId);
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_type_points', function (Blueprint $table) {
            $table->dropColumn('score_status');
        });
    }
};

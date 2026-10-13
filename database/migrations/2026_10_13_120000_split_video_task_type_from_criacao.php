<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Criação / Audiovisual" vira dois tipos: 'criacao' (Material Gráfico — mantém a chave, que
 * já está nos limites dos clientes, pontos e formatos) e 'video' (novo). Pedido do Evandro
 * em 2026-10-13 pra enxergar o gargalo de vídeo separado do volume de peças gráficas.
 *
 * Reclassifica pelo TÍTULO só o que tem sinal claro de vídeo. Palavras ambíguas ficam de
 * fora de propósito: "edição" (pega "Próxima Edição" de evento), "captação" sozinha (pega
 * "Captação de distribuidores" = captar cliente), "gravação" ("Bastidores da Gravação" é post),
 * "shorts" (roupa, cliente de moda). Captação só conta quando é claramente de filmagem.
 * Só mexe em tarefas de 'criacao' — vídeo cadastrado em outro tipo (tutorial de web etc.) fica.
 */
return new class extends Migration
{
    private const ACCENTS_FROM = 'áàâãäéèêëíìîïóòôõöúùûüç';
    private const ACCENTS_TO   = 'aaaaaeeeeiiiiooooouuuuc';

    public function up(): void
    {
        $t = "translate(lower(title), '" . self::ACCENTS_FROM . "', '" . self::ACCENTS_TO . "')";
        $isVideo = "(
            {$t} ~ '\\m(videos?|reels?|motion|animad[oa]s?|animac(ao|oes)|vinhetas?|filmage(m|ns))\\M'
            OR {$t} ~ '\\m(agendar|upload|organizar|organizacao|pre) captac'
            OR {$t} ~ '\\mcaptac(ao|oes) (audiovisual|de video|de videos|de fotos|regional)'
            OR {$t} ~ '\\m(decupar|material bruto)'
            OR ({$t} ~ '\\mteaser' AND {$t} !~ 'estatic')
        )";

        $ids = DB::table('tasks')->where('task_type', 'criacao')->whereRaw($isVideo)->pluck('id');

        // Histórico na própria tarefa (mesmo formato do task_type_changed manual), sem autor:
        // foi a reclassificação automática, não uma pessoa.
        $now = now();
        foreach ($ids->chunk(500) as $chunk) {
            DB::table('task_activities')->insert($chunk->map(fn ($id) => [
                'id'         => (string) Str::uuid(),
                'task_id'    => $id,
                'action'     => 'task_type_changed',
                'from_label' => 'Criação / Audiovisual',
                'to_label'   => 'Vídeo / Audiovisual',
                'user_id'    => null,
                'created_at' => $now,
            ])->all());

            DB::table('tasks')->whereIn('id', $chunk)->update(['task_type' => 'video']);
        }

        // Formatos de vídeo (Reels, Vídeo, Captação) passam pro tipo novo — mesmos pesos.
        DB::table('task_formats')->where('task_type', 'criacao')
            ->whereRaw("translate(lower(name), '" . self::ACCENTS_FROM . "', '" . self::ACCENTS_TO . "') ~ '(reels|video|captac)'")
            ->update(['task_type' => 'video', 'updated_at' => $now]);

        // Peso padrão e status de pontuação do tipo novo = os de criação de cada organização.
        foreach (DB::table('task_type_points')->where('task_type', 'criacao')->get() as $row) {
            $exists = DB::table('task_type_points')
                ->where('organization_id', $row->organization_id)->where('task_type', 'video')->exists();
            if (! $exists) {
                DB::table('task_type_points')->insert([
                    'id'              => (string) Str::uuid(),
                    'organization_id' => $row->organization_id,
                    'task_type'       => 'video',
                    'points'          => $row->points,
                    'score_status'    => $row->score_status,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('tasks')->where('task_type', 'video')->update(['task_type' => 'criacao']);
        DB::table('task_formats')->where('task_type', 'video')->update(['task_type' => 'criacao']);
        DB::table('task_type_points')->where('task_type', 'video')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Atualizar com reunião" — rodada de comentários (2026-11-06):
 * - comments: comentário geral + por item (sugestão, movimento, ponto em aberto) da rodada atual
 * - revision: quantas rodadas de revisão já rodaram (limite em DocumentUpdateService::MAX_REVISIONS)
 * - history: as rodadas anteriores (o que a IA tinha devolvido + os comentários que geraram a seguinte)
 * E o prompt do agente ganha as regras de preservação: avaliação do 1º resultado real mostrou a IA
 * reescrevendo blocos inteiros e apagando dado não decidido (verba lida em voz alta tratada como dúvida).
 */
return new class extends Migration
{
    private const MARK = 'PRESERVAR O QUE NÃO MUDOU';

    private const RULES = <<<'TXT'
PRESERVAR O QUE NÃO MUDOU (muito importante):
- Ao alterar um trecho, mexa só na parte que a reunião decidiu mudar. Todo o resto do trecho (frases, dados, números, nomes, explicações) volta igual, palavra por palavra. Se a decisão muda uma frase, o "trecho_novo" é o "trecho_atual" com aquela frase trocada.
- Use o menor trecho possível: se a mudança é numa frase ou num parágrafo, não reescreva o bloco inteiro.
- Dado concreto (metragem, valor, verba, data, quantidade, nome) só sai se a reunião decidiu tirar AQUELE dado. Decidir tirar um argumento não autoriza tirar outro que estava na mesma frase.
- Quando alguém na reunião lê ou resume o próprio documento em voz alta ("as verbas 30, 36", "cada tema ganha vídeo"), isso CONFIRMA o documento: não é dúvida nem mudança.
- A ATA pode marcar algo como "inconsistência" ou "a validar". Isso nunca é motivo para remover ou mudar o documento: confira na transcrição; se continuar duvidoso, vai para "pontos_em_aberto" e o documento fica como está.


TXT;

    public function up(): void
    {
        Schema::connection('pgsql')->table('document_update_runs', function (Blueprint $table) {
            if (!Schema::connection('pgsql')->hasColumn('document_update_runs', 'comments')) {
                $table->jsonb('comments')->nullable();
            }
            if (!Schema::connection('pgsql')->hasColumn('document_update_runs', 'revision')) {
                $table->unsignedSmallInteger('revision')->default(0);
            }
            if (!Schema::connection('pgsql')->hasColumn('document_update_runs', 'history')) {
                $table->jsonb('history')->nullable();
            }
        });

        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'document-updater')->first(['id', 'system_prompt']);
        if ($agent && !str_contains((string) $agent->system_prompt, self::MARK)) {
            $prompt = (string) $agent->system_prompt;
            $anchor = 'Linguagem: o time não é técnico.';
            $prompt = str_contains($prompt, $anchor)
                ? str_replace($anchor, self::RULES . $anchor, $prompt)
                : $prompt . "\n\n" . trim(self::RULES);

            DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)->update([
                'system_prompt' => $prompt,
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('document_update_runs', function (Blueprint $table) {
            $table->dropColumn(['comments', 'revision', 'history']);
        });
    }
};

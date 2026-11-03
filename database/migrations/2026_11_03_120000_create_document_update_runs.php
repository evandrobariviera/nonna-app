<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// "Atualizar documento com reunião" (DocumentUpdateService): a IA lê a ATA + transcrição,
// confere o HTML do planejamento/campanha/projeto e sugere as mudanças. Cada análise é uma
// linha aqui; nada muda no documento até alguém aceitar as sugestões (vira versão nova).
return new class extends Migration
{
    private const PROMPT = <<<'PROMPT'
Você é o redator sênior da Nonna Agência Digital responsável por manter vivos os documentos de planejamento, campanha e projeto. Depois de cada reunião, você confere o documento contra o que foi dito e propõe as mudanças necessárias — como faria quem escreveu o documento original com a skill da Nonna.

Você recebe:
- TIPO DO DOCUMENTO (planejamento, campanha ou projeto) e os dados atuais dele no App
- REGRAS DA SKILL: as mesmas instruções usadas para escrever esse tipo de documento (estrutura, seções, visual, bloco de dados, tom e regra de clareza). Siga-as em tudo o que escrever.
- REUNIÃO: tipo, data, ATA e transcrição
- DOCUMENTO ATUAL: o HTML do documento (estilos, scripts e imagens embutidas foram retirados; imagens aparecem como [imagem-embutida])
- DADOS ATUAIS: o bloco JSON "nonna-dados" que o App lê (datas, verba, peças, itens)
- PEDIDO DA EQUIPE (opcional): um foco ou cuidado específico

O que fazer:
1. Leia a reunião inteira e separe o que foi DECIDIDO (mudou data, entrou/saiu peça, mudou mensagem, verba, público, mecânica, escopo, prazo, responsável) do que só foi conversado.
2. Para cada decisão que afeta ESTE documento, proponha uma sugestão de mudança no HTML. Uma sugestão por decisão independente.
3. O que foi conversado mas não fechado (ou depende de alguém confirmar) vai em "pontos_em_aberto" — não vira sugestão.
4. Se a reunião não muda nada neste documento, devolva "sem_mudancas": true e a lista vazia. Não invente mudança para parecer útil.

Regras das sugestões:
- "trecho_atual" é copiado CARACTERE POR CARACTERE do DOCUMENTO ATUAL (mesmas tags, atributos, espaços, acentos e entidades). Ele precisa aparecer UMA ÚNICA VEZ no documento. Prefira um elemento inteiro e curto (um <p>, <li>, <tr>, um título com seu parágrafo) — no máximo uns 1.500 caracteres. Nunca use [imagem-embutida] no trecho.
- "trecho_novo" é o HTML que entra no lugar: mesmas classes e estrutura dos elementos vizinhos, para manter o visual do documento.
- Para INCLUIR algo novo (peça, linha de tabela, parágrafo), use como "trecho_atual" o elemento logo antes de onde entra, e em "trecho_novo" repita esse elemento e acrescente o novo depois dele.
- Para REMOVER, "trecho_novo" é vazio ("").
- Mude só o necessário. Não reescreva o que a reunião não tocou, não "melhore" o texto por conta própria.
- O que escrever segue a regra de clareza da skill: uma decisão por frase, com o motivo, palavras do dia a dia. O documento é lido pelo cliente: nada de bastidor interno, nomes de ferramentas internas ou fofoca da reunião.
- Nunca invente data, verba, número ou nome. Se a reunião deu uma data relativa ("semana que vem", "dia 15"), converta usando a data da reunião; se não der para ter certeza, vá para "pontos_em_aberto".
- "evidencia" é uma frase curta da ATA ou da transcrição que sustenta a mudança (pode resumir a fala).

Bloco de dados:
- Se alguma mudança mexe em datas, verba, peças, marcos, tarefas ou itens, devolva "dados_novos" com o bloco JSON COMPLETO já atualizado (todos os campos atuais, só com o que mudou alterado), seguindo as regras do bloco descritas na skill. O texto visível e o bloco precisam continuar batendo (mesmos nomes e datas).
- Se nada no bloco muda, "dados_novos": null.

Responda APENAS com JSON neste formato:
{
  "resumo": "2 a 4 frases: o que a reunião mudou neste documento (ou por que não muda nada)",
  "sem_mudancas": false,
  "sugestoes": [
    {
      "secao": "nome da seção do documento",
      "tipo": "alterar | incluir | remover",
      "titulo": "a mudança em até 80 caracteres",
      "motivo": "o que foi decidido na reunião e por que muda o documento",
      "evidencia": "trecho curto da ATA/transcrição",
      "trecho_atual": "HTML exato do documento",
      "trecho_novo": "HTML novo"
    }
  ],
  "dados_novos": null,
  "dados_motivo": "o que mudou no bloco de dados (ou null)",
  "pontos_em_aberto": ["..."]
}
PROMPT;

    public function up(): void
    {
        Schema::connection('pgsql')->create('document_update_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->string('owner_type', 20); // macro_plan | project
            $table->uuid('owner_id');
            $table->foreignUuid('meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
            $table->uuid('base_attachment_id')->nullable(); // versão do documento que a IA leu
            $table->text('instruction')->nullable();
            $table->string('status', 20)->default('processando'); // processando | pronto | erro | aplicado | descartado
            $table->text('summary')->nullable();
            $table->jsonb('suggestions')->nullable();
            $table->jsonb('data_after')->nullable();
            $table->text('data_reason')->nullable();
            $table->jsonb('open_points')->nullable();
            $table->text('error')->nullable();
            $table->uuid('applied_attachment_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'created_at']);
        });

        $this->seedAgent();
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('document_update_runs');
        DB::connection('pgsql')->table('ai_agents')->where('slug', 'document-updater')->delete();
    }

    private function seedAgent(): void
    {
        $db = DB::connection('pgsql');
        $provider = $db->table('ai_providers')->where('slug', 'openai')->first();
        if (!$provider || $db->table('ai_agents')->where('slug', 'document-updater')->exists()) {
            return;
        }

        $db->table('ai_agents')->insert([
            'id'            => (string) Str::uuid(),
            'slug'          => 'document-updater',
            'name'          => 'Documentos — Atualizar com Reunião',
            'description'   => 'Lê a ATA e a transcrição de uma reunião, confere o documento do planejamento, campanha ou projeto e sugere as mudanças (a equipe aceita uma a uma). Segue as regras das skills planejamento/campanha/projeto-nonna.',
            'system_prompt' => self::PROMPT,
            'provider_id'   => $provider->id,
            'api_key_id'    => null,
            'model'         => 'gpt-5.6-terra',
            'temperature'   => 0.3,
            // raciocínio + trechos de HTML na resposta: folga grande pra não voltar vazio
            'max_tokens'    => 32000,
            'context_scope' => 'global',
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
};

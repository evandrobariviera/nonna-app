<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Agente Nonna (2026-11-17): conversa em página inteira (estilo ChatGPT/Claude) com um
 * agente que consulta o App por "ferramentas" e propõe ações (tarefa, reunião) que a
 * pessoa confirma num cartão. Tabelas próprias — ai_chats é texto puro preso a uma
 * entidade; aqui cada mensagem pode ser chamada de ferramenta, resultado ou proposta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('agent_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_message_at']);
        });

        Schema::connection('pgsql')->create('agent_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('agent_conversations')->cascadeOnDelete();
            $table->string('role', 20);                 // user | assistant | tool
            $table->text('content')->nullable();
            $table->jsonb('tool_calls')->nullable();    // assistant: chamadas pedidas pela IA
            $table->string('tool_call_id', 100)->nullable();
            $table->string('tool_name', 60)->nullable();
            $table->jsonb('tool_args')->nullable();
            $table->jsonb('proposal')->nullable();      // ação que espera "Confirmar"
            $table->string('proposal_status', 20)->nullable(); // pending | confirmed | discarded
            $table->jsonb('proposal_result')->nullable();
            // Microssegundos: várias mensagens nascem no mesmo segundo (chamada +
            // resultados) e a ordem delas importa pra remontar o histórico.
            $table->timestamps(6);

            $table->index(['conversation_id', 'created_at']);
        });

        $this->seedAgent();
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('agent_messages');
        Schema::connection('pgsql')->dropIfExists('agent_conversations');
        DB::connection('pgsql')->table('ai_agents')->where('slug', 'nonna-agent')->delete();
    }

    private function seedAgent(): void
    {
        $db = DB::connection('pgsql');
        $provider = $db->table('ai_providers')->where('slug', 'openai')->first();
        if (!$provider || $db->table('ai_agents')->where('slug', 'nonna-agent')->exists()) {
            return;
        }

        $db->table('ai_agents')->insert([
            'id'            => (string) Str::uuid(),
            'slug'          => 'nonna-agent',
            'name'          => 'Agente Nonna — Assistente Universal',
            'description'   => 'Conversa em página inteira: consulta clientes, tarefas, reuniões e atas do App e propõe tarefas e reuniões para a pessoa confirmar.',
            'system_prompt' => self::PROMPT,
            'provider_id'   => $provider->id,
            'api_key_id'    => null,
            'model'         => 'gpt-5.6-terra',
            'temperature'   => 0.3,
            'max_tokens'    => 8000,
            'context_scope' => 'global',
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private const PROMPT = <<<'TXT'
Você é o Agente Nonna, assistente da equipe da Nonna Agência Digital dentro do Nonna App (o sistema onde ficam clientes, planejamentos, projetos/campanhas, tarefas, reuniões e atas).

QUEM VOCÊ ATENDE
A pessoa da equipe que está conversando com você (nome e id no contexto). Fale em português do Brasil, de forma direta e cordial, em linguagem do dia a dia — a equipe não é técnica. Respostas curtas; use listas quando ajudar. Nada de jargão de sistema (não fale "id", "tool", "JSON", "backlog" sem explicar).

COMO TRABALHAR
1. Você NÃO sabe nada dos clientes de cabeça. Sempre consulte com as ferramentas antes de afirmar algo. Nunca invente dado, nome, data ou número.
2. Quando a pessoa citar um cliente pelo nome, use buscar_clientes para achar o cliente certo. Se vier mais de um parecido, pergunte qual é. Se o cliente já foi identificado antes nesta conversa, reaproveite o client_id — não busque de novo.
3. Para "como está o cliente X", "me situa", "o que tem de pendente": use resumo_cliente e, se precisar, listar_tarefas, listar_reunioes e ler_ata.
4. Sempre que citar uma tarefa, reunião, cliente ou projeto, coloque o link que veio da ferramenta em formato markdown: [Título](link).
5. Datas: use a data de HOJE do contexto para entender "amanhã", "sexta", "semana que vem". Mostre datas como dd/mm (e o dia da semana quando for agenda).

AÇÕES (criar tarefa, agendar reunião)
6. Você nunca cria nada direto. Use propor_tarefa ou propor_reuniao: isso mostra um cartão para a pessoa conferir e clicar em "Confirmar". Depois de propor, diga em uma frase que o cartão está na tela para ela confirmar — NUNCA diga que já foi criado/agendado.
7. Antes de propor, junte o que falta. Tarefa precisa de: cliente, título claro e tipo. Reunião precisa de: título, tipo, data e hora. Deduza o tipo pelo assunto (criativos/peças/artes = criacao; vídeo/reels = video; anúncios/campanha paga = trafego; site/landing page = web; tag/pixel/GA4 = setup; posts/calendário = social; reunião de alinhamento com cliente = alinhamento_projeto) e só pergunte se for realmente ambíguo. Se a tarefa for claramente de um projeto/campanha que você já viu, use o project_id dela. Se faltar algo essencial, pergunte (uma pergunta só, objetiva). Se o resto não foi dito, deixe em branco — não invente executor, prazo ou participantes.
8. "Pro Fulano" numa tarefa = executor (quem faz). Responsável só se a pessoa disser explicitamente. Use apenas pessoas da lista da equipe do contexto; se o nome não existir, pergunte.
9. Escreva a descrição da tarefa pensando em quem vai executar: o que fazer, contexto do cliente que ajuda e o que é esperado na entrega. Curta e prática.
10. Antes de agendar reunião com pessoas da equipe, olhe a agenda delas no dia com listar_reunioes (filtro de pessoas e datas) e avise se houver choque de horário.
11. Se uma ferramenta devolver erro de validação, corrija e tente de novo; se não der, explique o problema para a pessoa em linguagem simples.
12. Se a proposta foi descartada pela pessoa, não insista — pergunte o que ela quer mudar.

LIMITES
13. Você só enxerga e faz o que as ferramentas permitem. Se pedirem algo fora disso (mandar WhatsApp, mexer no financeiro, alterar status de tarefa, Google Agenda), diga com franqueza que ainda não consegue fazer isso por aqui.
14. Nunca mostre informação de um cliente quando a pergunta for sobre outro.
TXT;
};

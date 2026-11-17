<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agente Nonna aprende pendências (listar_pendencias / propor_pendencia).
 * Acrescenta o bloco PENDÊNCIAS antes de LIMITES no prompt — idempotente.
 */
return new class extends Migration
{
    private const MARKER = "PENDÊNCIAS (coisas que alguém precisa entregar)";

    private const BLOCK = <<<'TXT'
PENDÊNCIAS (coisas que alguém precisa entregar)
P1. Pendência NÃO é tarefa de produção: é algo que alguém precisa entregar para o trabalho andar (material, informação, acesso, agendamento, decisão/aprovação, pagamento). "O cliente precisa mandar as fotos" = pendência; "fazer o post" = tarefa.
P2. Uma pendência tem 3 papéis: QUEM DEVE entregar (cliente, alguém do time ou terceiro), QUEM PRECISA (padrão: a pessoa da conversa) e QUEM COBRA (sempre uma pessoa do time). Use nomes de pessoas — nunca escolha "Atendimento" ou alguém por conta própria. Se a pessoa não disser quem cobra, PERGUNTE antes de propor (pode sugerir ela mesma: "Você mesmo cobra?").
P3. Se quem deve é o cliente e a pessoa citar o contato (ex.: "o João da BRDrive"), use o contact_id do resumo_cliente. Se quem deve é alguém do time, informe owed_user_id.
P4. Se a pendência trava uma tarefa ou campanha específica que você já viu, ligue a ela (task_id ou project_id). Deduza o tipo pelo assunto (fotos/vídeos/logo = insumo; senha/login/BM = acesso; dado/briefing = informacao; "aprovar"/"decidir" = decisao; boleto/verba = pagamento; marcar horário = agendamento).
P5. Para "o que o cliente está devendo", "o que eu tenho que cobrar", "pendências vencidas": use listar_pendencias (user_id = a pessoa da conversa para "minhas").

TXT;

    public function up(): void
    {
        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'nonna-agent')->first();
        if (!$agent || str_contains($agent->system_prompt, self::MARKER)) {
            return;
        }

        $prompt = str_contains($agent->system_prompt, 'LIMITES')
            ? str_replace('LIMITES', self::BLOCK . "\nLIMITES", $agent->system_prompt)
            : $agent->system_prompt . "\n\n" . self::BLOCK;

        // "Ações" agora inclui pendência.
        $prompt = str_replace('AÇÕES (criar tarefa, agendar reunião)', 'AÇÕES (criar tarefa, agendar reunião, registrar pendência)', $prompt);
        $prompt = str_replace('Use propor_tarefa ou propor_reuniao:', 'Use propor_tarefa, propor_reuniao ou propor_pendencia:', $prompt);

        DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)->update([
            'system_prompt' => $prompt,
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'nonna-agent')->first();
        if (!$agent) {
            return;
        }

        DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)->update([
            'system_prompt' => str_replace([self::BLOCK . "\n", 'AÇÕES (criar tarefa, agendar reunião, registrar pendência)', 'Use propor_tarefa, propor_reuniao ou propor_pendencia:'],
                ['', 'AÇÕES (criar tarefa, agendar reunião)', 'Use propor_tarefa ou propor_reuniao:'], $agent->system_prompt),
        ]);
    }
};

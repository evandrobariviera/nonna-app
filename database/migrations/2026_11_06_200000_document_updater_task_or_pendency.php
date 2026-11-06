<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Atualizar com reunião": cada item de "pendencias" ganha "natureza" — tarefa (a agência
 * produz algo) ou pendencia (esperar/cobrar algo de alguém). No painel, o item já vem
 * marcado e o botão principal é "Criar tarefa" ou "Registrar pendência" (2026-11-06).
 * Idempotente.
 */
return new class extends Migration
{
    private const MARK = '- "natureza":';

    private const AFTER = '- "origem": aberto (ponto em aberto) | nova_demanda | fora (fora deste documento).';
    private const RULE  = "\n" . '- "natureza": "tarefa" quando a AGÊNCIA vai PRODUZIR algo (peça, texto, vídeo, análise, proposta, material, ajuste no site) — vira tarefa com executor. "pendencia" quando é ESPERAR ou COBRAR algo de alguém (enviar, confirmar, liberar, responder, decidir, aprovar), sem produção da agência. Se uma demanda tem as duas partes (ex.: pedir o material ao cliente e depois produzir o onboarding), liste as duas separadas, cada uma com a sua natureza.';

    private const FORMAT_FROM = '"origem": "aberto | nova_demanda | fora", "contexto": "uma frase" }';
    private const FORMAT_TO   = '"origem": "aberto | nova_demanda | fora", "natureza": "tarefa | pendencia", "contexto": "uma frase" }';

    public function up(): void
    {
        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'document-updater')->first(['id', 'system_prompt']);
        if (!$agent || str_contains($agent->system_prompt, self::MARK) || !str_contains($agent->system_prompt, self::AFTER)) {
            return;
        }

        $prompt = str_replace(self::AFTER, self::AFTER . self::RULE, $agent->system_prompt);
        $prompt = str_replace(self::FORMAT_FROM, self::FORMAT_TO, $prompt);

        DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)->update(['system_prompt' => $prompt, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'document-updater')->first(['id', 'system_prompt']);
        if (!$agent) {
            return;
        }

        $prompt = str_replace([self::RULE, self::FORMAT_TO], ['', self::FORMAT_FROM], $agent->system_prompt);
        DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)->update(['system_prompt' => $prompt, 'updated_at' => now()]);
    }
};

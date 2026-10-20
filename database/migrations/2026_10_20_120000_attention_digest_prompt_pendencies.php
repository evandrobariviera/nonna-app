<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // "Seu foco" passou a receber pendencias_vencidas (AttentionDigestService). Ensina o
    // agente o que é — só se o prompt ainda tem a linha original (se alguém editou em
    // Agentes & IA, não mexe).
    private const OLD = '4. atrasadas — tarefas dela com data de aprovação vencida.';

    private const NEW = '4. atrasadas — tarefas dela com data de aprovação vencida.' . "\n"
        . '4.1 pendencias_vencidas — pendências com prazo vencido ou vencendo hoje: algo que alguém precisa ENTREGAR pra o trabalho andar (material do cliente, acesso, data, decisão). papel "entregar" = o time está esperando isso DELA; papel "cobrar" = ela é quem vai atrás de quem deve (campo "deve"). Quanto mais dias vencida, pior; se "ja_cobrada_vezes" é 0, ninguém cobrou ainda.';

    public function up(): void
    {
        $db    = DB::connection('pgsql');
        $agent = $db->table('ai_agents')->where('slug', 'attention-digest')->first();
        if (!$agent || !str_contains($agent->system_prompt, self::OLD) || str_contains($agent->system_prompt, 'pendencias_vencidas')) {
            return;
        }

        $db->table('ai_agents')->where('id', $agent->id)->update([
            'system_prompt' => str_replace(self::OLD, self::NEW, $agent->system_prompt),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        $db    = DB::connection('pgsql');
        $agent = $db->table('ai_agents')->where('slug', 'attention-digest')->first();
        if ($agent) {
            $db->table('ai_agents')->where('id', $agent->id)->update([
                'system_prompt' => str_replace(self::NEW, self::OLD, $agent->system_prompt),
            ]);
        }
    }
};

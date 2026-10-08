<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ASSISTANT_NAME = 'Assistente de Lançamento de Tarefas';

    // Trocas no prompt do Assistente: o rascunho passa a trazer prazo como
    // data de verdade (due_date) em vez de "dias depois do início do projeto"
    // — com a data de hoje no contexto, "pra sexta" vira a sexta certa.
    private const PROMPT_SWAPS = [
        '"due_offset_days": 0,' => '"due_date": "AAAA-MM-DD",',
        '8. "due_offset_days" é sempre um número de dias relativo à data de início do projeto (ou a hoje, se o projeto não tiver data de início definida) — nunca uma data absoluta.'
            => '8. "due_date" é sempre uma data absoluta no formato AAAA-MM-DD, calculada a partir da data de HOJE informada no contexto (ex: "pra sexta" = a próxima sexta-feira a partir de hoje; "em 2 semanas" = hoje + 14 dias). Se o usuário não falar de prazo, use null.',
    ];

    // slug = identificador fixo pro código achar o agente certo sem depender
    // do nome (que pode ser editado na tela). Só agentes "de sistema" têm.
    public function up(): void
    {
        Schema::connection('pgsql')->table('ai_agents', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
        });

        $agent = DB::connection('pgsql')->table('ai_agents')->where('name', self::ASSISTANT_NAME)->first();
        if (!$agent) {
            return;
        }

        DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)->update([
            'slug'          => 'task-assistant',
            'system_prompt' => strtr($agent->system_prompt, self::PROMPT_SWAPS),
        ]);
    }

    public function down(): void
    {
        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'task-assistant')->first();
        if ($agent) {
            DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)->update([
                'system_prompt' => strtr($agent->system_prompt, array_flip(self::PROMPT_SWAPS)),
            ]);
        }

        Schema::connection('pgsql')->table('ai_agents', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};

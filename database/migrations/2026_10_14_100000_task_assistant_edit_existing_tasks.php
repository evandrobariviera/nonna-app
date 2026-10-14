<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Assistente de Lançamento passa a VER e REORGANIZAR as tarefas que já existem no
    // projeto (pedido do Evandro, 2026-10-14): recebe a lista inteira com refs (#1, #2...),
    // a carga do time por dia, e devolve "task_changes" — alterações que a pessoa revisa e
    // aplica. Acrescenta ao prompt atual em vez de sobrescrever (o prompt é editável na tela
    // de Agentes de IA); idempotente pela presença de "task_changes".
    private const FORMAT_FROM = '"functional_role_key": "..."}]}';
    private const FORMAT_TO   = '"functional_role_key": "..."}], "task_changes": [{"ref": "#12", "field": "approval_date", "value": "AAAA-MM-DD", "reason": "..."}]}';

    private const SECTION = <<<'TXT'

TAREFAS QUE JÁ EXISTEM — VER E REORGANIZAR

14. Você recebe "Tarefas do projeto" com TODAS as tarefas existentes, cada uma com uma referência (#1, #2...), tipo, status, executor, responsável, datas, sprint e se tem TRAVA. Use essa lista pra responder perguntas sobre a organização do projeto (o que está atrasado, o que está sem data, quem está com o quê) — responda direto, citando as tarefas pela referência e pelo título.

15. Pra mudar tarefas que JÁ EXISTEM, use "task_changes" (nunca "draft_tasks" — esse é só pra criar tarefas novas). Cada item muda UM campo de UMA tarefa: {"ref": "#12", "field": "...", "value": ..., "reason": "por que, em poucas palavras"}. Campos permitidos: "approval_date", "due_date", "publish_date" (AAAA-MM-DD, ou null pra limpar), "executor_user_id", "responsavel_user_id" (id da Equipe, ou null), "title", "description". Use só refs da lista. Nada é aplicado de verdade: a pessoa revisa a lista na tela, desmarca o que não quiser e clica em "Aplicar".

16. Você NÃO muda status, tipo, prioridade nem sprint, e não cancela nem exclui tarefa. Se pedirem, explique que isso é feito na própria tarefa (status dispara aprovação e avisos pro cliente).

17. "task_changes" é sempre a lista COMPLETA de alterações pendentes depois da sua resposta (veja "ALTERAÇÕES PROPOSTAS NA TELA"): ao ajustar ("tira a #5", "a #3 deixa pro dia 20"), devolva todas, não só as novas. null = não mexeu nas alterações; [] = descartar todas. Quando a conversa diz que alterações foram aplicadas (✓), elas já valem — a lista de tarefas que você recebe já está atualizada.

18. Datas: a "data de aprovação" (approval_date) é o prazo de quem executa — é ela que organiza a semana de cada pessoa na Execução e na Distribuição. Tarefa ATRASADA = aberta com data de aprovação antes de HOJE (é a régua do sistema inteiro; não use o vencimento pra dizer que está atrasada). Vencimento (due_date) é o prazo final e publicação (publish_date) é quando vai ao ar. Quando as três existirem, mantenha aprovação ≤ vencimento ≤ publicação — se mover a aprovação pra depois do vencimento, proponha também o novo vencimento (e a publicação, se for o caso). Não use sábado nem domingo e não proponha data antes de HOJE.

19. Ao distribuir datas, a carga de uma pessoa num dia = o que ela tem em OUTROS projetos nesse dia ("Carga do time por dia") + as tarefas DESTE projeto que ficam com aprovação nesse dia (as que você não move continuam na data atual da lista; as que você move saem do dia antigo). Não empilhe muitas tarefas no mesmo dia pra mesma pessoa, espalhe pelos dias úteis e respeite o limite que a pessoa pedir ("no máximo 3 por dia" = no total do dia, contando os outros projetos). Se não couber até a data pedida, distribua o que couber e diga na "message" quantas ficaram de fora e por quê, em vez de forçar ou desistir.

20. Não proponha mudança em tarefa Concluída. Tarefa em Aprovação, Despacho ou com TRAVA pode ter data alterada, mas avise na "message".

21. Na "message", diga o critério usado (ex: "Espalhei as 12 peças entre 15/10 e 24/10, no máximo 2 por dia pro Andreo, que já tem 9 na quinta") sem listar tarefa por tarefa — as alterações aparecem na tela.
TXT;

    public function up(): void
    {
        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'task-assistant')->first(['id', 'system_prompt']);
        if (!$agent || str_contains((string) $agent->system_prompt, 'task_changes')) {
            return;
        }

        $prompt = str_replace(self::FORMAT_FROM, self::FORMAT_TO, (string) $agent->system_prompt) . "\n" . self::SECTION;

        DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)->update(['system_prompt' => $prompt]);
    }

    public function down(): void
    {
        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'task-assistant')->first(['id', 'system_prompt']);
        if (!$agent) {
            return;
        }

        $prompt = str_replace([self::FORMAT_TO, "\n" . self::SECTION], [self::FORMAT_FROM, ''], (string) $agent->system_prompt);
        DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)->update(['system_prompt' => $prompt]);
    }
};

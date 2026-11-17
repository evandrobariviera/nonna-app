<?php

namespace App\Services\Agent;

use App\Models\AgentConversation;
use App\Models\AgentMessage;
use App\Models\AiAgent;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * O "motor" do Agente Nonna: manda a conversa pra IA junto com as ferramentas;
 * se ela pedir ferramentas, executa, grava o resultado e chama de novo — até ela
 * responder em texto (ou bater MAX_STEPS, quando é obrigada a responder sem
 * ferramenta). Tudo fica gravado em agent_messages no formato da OpenAI, então a
 * próxima mensagem já tem o histórico inteiro, inclusive o que foi consultado.
 */
class AgentRunner
{
    private const MAX_STEPS = 8;
    private const HISTORY_WINDOW = 60;     // mensagens (começando sempre numa da pessoa)
    private const TOOL_RESULT_MAX = 15000; // caracteres por resultado de ferramenta
    private const OLD_TOOL_RESULT_MAX = 2500; // resultados de perguntas anteriores

    public function __construct(private AiService $ai) {}

    /** @return Collection<AgentMessage> mensagens criadas nesta rodada (inclui a da pessoa) */
    public function reply(AgentConversation $conversation, User $user, string $text): Collection
    {
        $agent = AiAgent::bySlug(AiAgent::SLUG_NONNA_AGENT);
        if (!$agent) {
            throw new \RuntimeException('O Agente Nonna não está configurado ou está desativado (Agentes & IA → Agentes).');
        }

        $toolbox = new AgentToolbox($user);
        $tools = $toolbox->definitions();
        $created = collect();

        $created->push($conversation->messages()->create(['role' => 'user', 'content' => $text]));
        if (!$conversation->title) {
            $conversation->title = Str::limit(trim(preg_replace('/\s+/', ' ', $text)), 70);
        }
        $conversation->last_message_at = now();
        $conversation->save();

        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            // Instruções fixas no começo (a OpenAI reaproveita do cache, ~10% do preço);
            // a hora exata vai no FIM — no começo ela mudava a cada minuto e quebrava o cache.
            $messages = array_merge(
                [['role' => 'system', 'content' => $this->systemPrompt($agent, $user, $toolbox)]],
                $this->history($conversation),
                [['role' => 'system', 'content' => 'Agora: ' . now()->locale('pt_BR')->isoFormat('dddd, DD/MM/YYYY HH:mm') . ' (horário de Brasília).']],
            );

            $reply = $this->ai->chatWithTools(
                agent: $agent,
                messages: $messages,
                tools: $tools,
                userId: $user->id,
                allowTools: $step < self::MAX_STEPS - 1,
            );

            $calls = array_values(array_filter($reply['tool_calls'] ?? [], fn ($c) => ($c['type'] ?? 'function') === 'function'));
            $content = is_string($reply['content'] ?? null) ? trim($reply['content']) : null;

            if (!$calls && ($content === null || $content === '')) {
                $content = 'Não consegui montar uma resposta agora. Pode reformular o pedido?';
            }

            $created->push($conversation->messages()->create([
                'role'       => 'assistant',
                'content'    => $content ?: null,
                'tool_calls' => $calls ?: null,
            ]));

            if (!$calls) {
                break;
            }

            foreach ($calls as $call) {
                $name = $call['function']['name'] ?? '';
                $args = json_decode($call['function']['arguments'] ?? '{}', true);
                $out  = $toolbox->call($name, is_array($args) ? $args : []);

                $created->push($conversation->messages()->create([
                    'role'            => 'tool',
                    'tool_call_id'    => $call['id'],
                    'tool_name'       => $name,
                    'tool_args'       => is_array($args) ? $args : null,
                    'content'         => Str::limit(json_encode($out['result'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), self::TOOL_RESULT_MAX),
                    'proposal'        => $out['proposal'] ?? null,
                    'proposal_status' => isset($out['proposal']) ? 'pending' : null,
                ]));
            }
        }

        $conversation->update(['last_message_at' => now()]);

        return $created;
    }

    private function systemPrompt(AiAgent $agent, User $user, AgentToolbox $toolbox): string
    {
        $now = now()->locale('pt_BR');

        $team = User::whereNull('client_id')
            ->with('functionalRoles:id,name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($u) {
                $roles = $u->functionalRoles->pluck('name')->implode(', ');
                return "{$u->id}: {$u->name}" . ($roles ? " ({$roles})" : '');
            })
            ->implode('; ');

        return $agent->system_prompt . "\n\n---\nCONTEXTO ATUAL\n"
            . "Hoje: {$now->isoFormat('dddd, DD/MM/YYYY')} (data: {$now->format('Y-m-d')}; a hora exata vem no fim da conversa)\n"
            . "Pessoa na conversa: {$user->name} (id {$user->id})\n"
            . "Equipe (id: Nome (papéis)): {$team}";
    }

    /** Histórico no formato da OpenAI, sempre começando numa mensagem da pessoa. */
    private function history(AgentConversation $conversation): array
    {
        $rows = $conversation->messages()->reorder()->latest('created_at')->limit(self::HISTORY_WINDOW)->get()->reverse()->values();

        $firstUser = $rows->search(fn ($m) => $m->role === 'user');
        $rows = $firstUser === false ? collect() : $rows->slice($firstUser)->values();
        $lastUser = $rows->keys()->filter(fn ($i) => $rows[$i]->role === 'user')->last();

        return $rows->map(function (AgentMessage $m, int $i) use ($lastUser) {
            return match ($m->role) {
                'user'      => ['role' => 'user', 'content' => (string) $m->content],
                'assistant' => $m->tool_calls
                    ? ['role' => 'assistant', 'content' => $m->content, 'tool_calls' => $m->tool_calls]
                    : ['role' => 'assistant', 'content' => (string) $m->content],
                'tool'      => [
                    'role'         => 'tool',
                    'tool_call_id' => $m->tool_call_id,
                    'content'      => $this->toolContent($m, $i < $lastUser),
                ],
                default     => null,
            };
        })->filter()->values()->all();
    }

    /** Resultado da ferramenta; numa proposta, diz o que a pessoa fez com o cartão. */
    private function toolContent(AgentMessage $m, bool $old): string
    {
        $content = (string) $m->content;
        if ($old) {
            $content = Str::limit($content, self::OLD_TOOL_RESULT_MAX);
        }

        if ($m->proposal) {
            $content .= "\nSITUAÇÃO DO CARTÃO: " . match ($m->proposal_status) {
                'confirmed' => 'a pessoa CONFIRMOU — ' . ($m->proposal_result['label'] ?? 'criado') . ' (' . ($m->proposal_result['url'] ?? '') . ')',
                'discarded' => 'a pessoa DESCARTOU, nada foi criado.',
                default     => 'aguardando a pessoa confirmar — ainda não foi criado.',
            };
        }

        return $content;
    }
}

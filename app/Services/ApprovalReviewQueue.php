<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\TaskApprovalToken;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * "Revisar pendentes": o contato passa pelas peças que estão esperando A
 * RESPOSTA DELE, uma atrás da outra. Cada peça abre na página do link dele
 * (/aprovar/{token}) — a mesma de sempre, com a regra de todos os
 * aprovadores — e depois de responder vai sozinho pra próxima. No fim volta
 * pra Central/Projeto de onde saiu.
 *
 * A fila fica na sessão (lista de tokens); a decisão continua sendo por
 * tarefa, via TaskApprovalService::submitDecision().
 */
class ApprovalReviewQueue
{
    private const KEY = 'approval_review';

    public function __construct(private TaskApprovalService $approvals) {}

    /**
     * Links válidos e ainda sem resposta do contato, na ordem dos cards.
     *
     * @param  Collection<int, array>  $items  cards do ProjectApprovalPageService
     * @return Collection<int, TaskApprovalToken>
     */
    public function pendingTokens(Contact $contact, Collection $items): Collection
    {
        $roundIds = $items->where('status', 'pending')->pluck('round.id')->values();

        $tokens = TaskApprovalToken::where('contact_id', $contact->id)
            ->whereIn('round_id', $roundIds)
            ->where('will_notify', true)
            ->get()
            ->filter(fn (TaskApprovalToken $t) => $t->isValid())
            ->keyBy('round_id');

        return $roundIds->map(fn ($id) => $tokens->get($id))->filter()->values();
    }

    public function start(Request $request, Collection $tokens, string $backUrl): ?string
    {
        if ($tokens->isEmpty()) {
            return null;
        }

        $request->session()->put(self::KEY, [
            'tokens' => $tokens->pluck('token')->all(),
            'back'   => $backUrl,
            'done'   => 0,
        ]);

        return $tokens->first()->token;
    }

    /**
     * Estado da revisão pra barra no topo da página do link — null se essa
     * peça não faz parte de uma revisão em andamento.
     *
     * @return array{pos: int, total: int, prev: ?string, next: ?string, back: string, dots: array}|null
     */
    public function state(Request $request, TaskApprovalToken $current): ?array
    {
        $queue = $request->session()->get(self::KEY);
        if (!$queue || ($i = array_search($current->token, $queue['tokens'], true)) === false) {
            return null;
        }

        $statuses = TaskApprovalToken::whereIn('token', $queue['tokens'])->pluck('status', 'token');

        return [
            'pos'   => $i + 1,
            'total' => count($queue['tokens']),
            'prev'  => $queue['tokens'][$i - 1] ?? null,
            'next'  => $queue['tokens'][$i + 1] ?? null,
            'back'  => $queue['back'],
            'dots'  => array_map(fn ($t, $k) => [
                'token'   => $t,
                'status'  => $statuses[$t] ?? 'pending',
                'current' => $k === $i,
            ], $queue['tokens'], array_keys($queue['tokens'])),
        ];
    }

    /**
     * Depois de responder uma peça da fila: a próxima ainda sem resposta
     * (pra frente primeiro, depois as que ficaram puladas pra trás). Sem
     * próxima, encerra e devolve null.
     *
     * @return array{next: ?string, back: string, done: int}|null  null = não estava em revisão
     */
    public function advance(Request $request, TaskApprovalToken $answered): ?array
    {
        $queue = $request->session()->get(self::KEY);
        if (!$queue || ($i = array_search($answered->token, $queue['tokens'], true)) === false) {
            return null;
        }

        $queue['done']++;

        $open = TaskApprovalToken::whereIn('token', $queue['tokens'])
            ->get()
            ->filter(fn ($t) => $t->isValid())
            ->pluck('token')
            ->all();

        $ordered = array_merge(array_slice($queue['tokens'], $i + 1), array_slice($queue['tokens'], 0, $i));
        $next    = collect($ordered)->first(fn ($t) => in_array($t, $open, true));

        if ($next) {
            $request->session()->put(self::KEY, $queue);
        } else {
            $request->session()->forget(self::KEY);
        }

        return ['next' => $next, 'back' => $queue['back'], 'done' => $queue['done']];
    }

    public function stop(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }

    /**
     * "Aprovar todas": aprova, uma a uma, as peças que esperam a resposta do
     * contato — cada uma no próprio link, pela mesma regra de sempre.
     */
    public function approveAll(Collection $tokens): int
    {
        foreach ($tokens as $token) {
            $this->approvals->submitDecision($token, 'approved', null);
        }

        return $tokens->count();
    }
}

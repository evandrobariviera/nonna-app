<?php

namespace App\Services\Dashboard;

use App\Models\AiAgent;
use App\Models\AttentionDigest;
use App\Models\InternalNotification;
use App\Models\Meeting;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * "Seu foco" — resumo de atenção de cada pessoa (Dashboard, todo modo), gerado 2x/dia.
 *
 * Divisão de papéis (decisão de 2026-10-09): QUEM decide o que importa são as REGRAS
 * abaixo (collect), sempre na mesma ordem de peso — Travas > ATA a ler > Revisão
 * Interna pra eu revisar > atrasadas > vence hoje > reuniões de hoje > outras
 * pendências. A IA só escolhe as 3-5 mais importantes dessa lista e escreve o "porquê"
 * em linguagem de gente. Cada item tem um ref (T1, A1...) e o link é resolvido aqui,
 * nunca pela IA — ref inventado é descartado. Se a IA falhar, o foco sai das regras.
 */
class AttentionDigestService
{
    private const LIST_LIMIT = 8;

    public function __construct(private AiService $ai) {}

    public function generate(User $user, string $organizationId): AttentionDigest
    {
        [$items, $links] = $this->collect($user);

        $digest = new AttentionDigest([
            'organization_id' => $organizationId,
            'user_id'         => $user->id,
            'generated_at'    => now(),
            'items'           => $items,
        ]);

        if ($this->isEmpty($items)) {
            $digest->fill(['source' => 'empty', 'opening' => 'Nada travando nem atrasado com você agora. Bom momento pra adiantar o que vem pela frente.', 'focus' => []]);
            $digest->save();
            return $digest;
        }

        $agent = AiAgent::bySlug(AiAgent::SLUG_ATTENTION_DIGEST);

        try {
            if (!$agent) {
                throw new \RuntimeException('Agente "' . AiAgent::SLUG_ATTENTION_DIGEST . '" não encontrado ou inativo.');
            }

            $answer = $this->ai->runStructured(
                $agent,
                json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                [],
                $user->id,
                null,
                'attention_digest'
            );

            $focus = collect($answer['focos'] ?? [])
                ->filter(fn ($f) => is_array($f) && isset($links[$f['ref'] ?? '']))
                ->unique('ref')
                ->take(5)
                ->map(fn ($f) => [
                    'ref'    => $f['ref'],
                    'title'  => mb_substr(trim((string) ($f['titulo'] ?? '')), 0, 160),
                    'why'    => mb_substr(trim((string) ($f['porque'] ?? '')), 0, 300),
                    'url'    => $links[$f['ref']]['url'],
                    'kind'   => $links[$f['ref']]['kind'],
                ])
                ->filter(fn ($f) => $f['title'] !== '')
                ->values()
                ->all();

            if (empty($focus)) {
                throw new \RuntimeException('A IA não devolveu nenhum foco válido.');
            }

            $digest->fill([
                'source'   => 'ai',
                'opening'  => mb_substr(trim((string) ($answer['abertura'] ?? '')), 0, 300) ?: null,
                'focus'    => $focus,
                'can_wait' => mb_substr(trim((string) ($answer['pode_esperar'] ?? '')), 0, 300) ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AttentionDigest: IA falhou, usando regras', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $digest->fill([
                'source' => 'rules',
                'focus'  => $this->rulesFocus($items, $links),
                'error'  => mb_substr($e->getMessage(), 0, 1000),
            ]);
        }

        $digest->save();
        return $digest;
    }

    /**
     * Monta a lista (o que a IA recebe) + o mapa ref → link. Tudo recortado pra pessoa.
     *
     * @return array{0: array, 1: array<string, array{url: string, kind: string}>}
     */
    public function collect(User $user): array
    {
        $userId = (int) $user->id;
        $now    = now();
        $links  = [];
        $ref    = function (string $prefix, int $i, string $url, string $kind) use (&$links): string {
            $key = $prefix . ($i + 1);
            $links[$key] = ['url' => $url, 'kind' => $kind];
            return $key;
        };
        $client = fn ($model) => $model->client?->displayName();
        $openStatuses = ['backlog', 'em_producao', 'ajuste_alteracao'];
        $activeSprint = Sprint::where('status', 'active')->first();

        // 1. Travas — outras pessoas esperando
        $blockers = Task::blockersFor($userId)->with(['client', 'executor'])->get();
        $travas = $blockers->take(self::LIST_LIMIT)->values()->map(fn ($t, $i) => [
            'ref'           => $ref('T', $i, route('tasks.show', $t), 'trava'),
            'tarefa'        => $t->title,
            'cliente'       => $client($t),
            'travando_ha_dias' => (int) $t->blocker_at->copy()->startOfDay()->diffInDays(today()),
            'executor'      => (string) $t->executor_id === (string) $userId ? 'você' : $t->executor?->name,
            'status'        => $t->statusLabel(),
        ])->all();

        // 2. ATAs a ler (aviso "Li a ATA" ainda não confirmado)
        $atas = InternalNotification::where('user_id', $userId)
            ->where('kind', InternalNotification::KIND_ATA)
            ->where('status', 'novo')
            ->orderBy('generated_at')
            ->get();
        $ataMeetings = Meeting::whereIn('id', $atas->where('source_type', 'Meeting')->pluck('source_id'))->with('client')->get()->keyBy('id');
        $atasItems = $atas->take(self::LIST_LIMIT)->values()->map(fn ($n, $i) => [
            'ref'       => $ref('A', $i, $n->link ?: route('dashboard'), 'ata'),
            'reuniao'   => $ataMeetings->get($n->source_id)?->title ?? $n->title,
            'cliente'   => ($m = $ataMeetings->get($n->source_id)) ? $client($m) : null,
            'liberada_ha_horas' => (int) $n->generated_at->diffInHours($now),
        ])->all();

        // 3. Revisão Interna esperando EU revisar (sou Responsável)
        $reviews = Task::where('status', 'revisao_interna')
            ->whereHas('responsibles', fn ($q) => $q->where('users.id', $userId))
            ->where(fn ($q) => $q->whereNull('client_id')->orWhereHas('client', fn ($c) => $c->where('status', '!=', 'inactive')))
            ->with(['client', 'executor'])
            ->orderBy('approval_date')
            ->get();
        $reviewItems = $reviews->take(self::LIST_LIMIT)->values()->map(fn ($t, $i) => [
            'ref'      => $ref('R', $i, route('tasks.show', $t), 'revisao'),
            'tarefa'   => $t->title,
            'cliente'  => $client($t),
            'feita_por' => $t->executor?->name,
            'data_aprovacao' => $t->approval_date?->format('d/m'),
        ])->all();

        // 4/5. Atrasadas e que vencem hoje (sou executor; data de aprovação; sprint ativa)
        $mine = fn () => $this->executedBy($userId)
            ->whereIn('status', $openStatuses)
            ->whereNull('blocker_at') // trava já entrou em cima
            ->when($activeSprint, fn ($q) => $q->where('sprint_id', $activeSprint->id));
        $overdue = $mine()->whereDate('approval_date', '<', today())->with('client')->orderBy('approval_date')->get();
        $dueToday = $mine()->whereDate('approval_date', today())->with('client')->orderBy('title')->get();
        $taskRow = fn (string $prefix, string $kind) => fn ($t, $i) => [
            'ref'     => $ref($prefix, $i, route('tasks.show', $t), $kind),
            'tarefa'  => $t->title,
            'cliente' => $client($t),
            'status'  => $t->statusLabel(),
            'data_aprovacao' => $t->approval_date?->format('d/m'),
            'prioridade' => $t->priority && $t->priority !== 'normal' ? $t->priorityLabel() : null,
        ];
        $overdueItems  = $overdue->take(self::LIST_LIMIT)->values()->map($taskRow('X', 'atrasada'))->all();
        $dueTodayItems = $dueToday->take(self::LIST_LIMIT)->values()->map($taskRow('H', 'hoje'))->all();

        // 6. Reuniões de hoje que ainda não passaram
        $meetings = Meeting::where(fn ($q) => $q->where('organized_by', $userId)->orWhereHas('participants', fn ($p) => $p->where('users.id', $userId)))
            ->whereNotIn('status', ['realizada', 'cancelada'])
            ->whereDate('scheduled_at', today())
            ->where('scheduled_at', '>=', $now->copy()->subMinutes(30))
            ->with('client')
            ->orderBy('scheduled_at')
            ->get();
        $meetingItems = $meetings->take(self::LIST_LIMIT)->values()->map(fn ($m, $i) => [
            'ref'     => $ref('M', $i, route('meetings.show', $m), 'reuniao'),
            'reuniao' => $m->title,
            'tipo'    => Meeting::$types[$m->type] ?? $m->type,
            'cliente' => $client($m),
            'horario' => $m->scheduled_at->format('H:i'),
        ])->all();

        // 7. Outras pendências (notificações novas que não são ATA)
        $others = InternalNotification::where('user_id', $userId)
            ->where('status', 'novo')
            ->where(fn ($q) => $q->whereNull('kind')->orWhere('kind', '!=', InternalNotification::KIND_ATA))
            ->orderBy('generated_at')
            ->get();
        $otherItems = $others->take(self::LIST_LIMIT)->values()->map(fn ($n, $i) => [
            'ref'    => $ref('P', $i, $n->link ?: route('dashboard'), 'pendencia'),
            'aviso'  => $n->title,
            'texto'  => $n->body ? mb_substr($n->body, 0, 140) : null,
            'ha_dias' => (int) $n->generated_at->copy()->startOfDay()->diffInDays(today()),
        ])->all();

        $items = [
            'pessoa'  => $user->name,
            'agora'   => $now->locale('pt_BR')->translatedFormat('l, d/m/Y \à\s H:i'),
            'momento' => $now->hour < 11 ? 'manha' : 'meio_dia',
            'travas'                 => ['total' => $blockers->count(), 'itens' => $travas],
            'atas_para_ler'          => ['total' => $atas->count(), 'itens' => $atasItems],
            'revisao_interna_para_revisar' => ['total' => $reviews->count(), 'itens' => $reviewItems],
            'atrasadas'              => ['total' => $overdue->count(), 'itens' => $overdueItems],
            'vencem_hoje'            => ['total' => $dueToday->count(), 'itens' => $dueTodayItems],
            'reunioes_hoje'          => ['total' => $meetings->count(), 'itens' => $meetingItems],
            'outras_pendencias'      => ['total' => $others->count(), 'itens' => $otherItems],
        ];

        return [$items, $links];
    }

    private function executedBy(int $userId): Builder
    {
        // Mesma régua do DashboardController::executorTasksQuery — só papel "executor".
        return Task::where(fn ($q) => $q
            ->where('executor_id', $userId)
            ->orWhereHas('executors', fn ($q2) => $q2->where('users.id', $userId)->where('task_executors.role', 'executor')));
    }

    private function isEmpty(array $items): bool
    {
        foreach (['travas', 'atas_para_ler', 'revisao_interna_para_revisar', 'atrasadas', 'vencem_hoje', 'reunioes_hoje', 'outras_pendencias'] as $k) {
            if (($items[$k]['total'] ?? 0) > 0) {
                return false;
            }
        }
        return true;
    }

    // Sem IA: os 5 primeiros na ordem de peso das regras, com um "porquê" padrão.
    private function rulesFocus(array $items, array $links): array
    {
        $why = [
            'travas'        => fn ($r) => 'Trava: tem gente esperando' . ($r['travando_ha_dias'] > 0 ? " há {$r['travando_ha_dias']} dia(s)." : ' desde hoje.'),
            'atas_para_ler' => fn ($r) => 'ATA liberada pra Revisão Interna — confirme "Li a ATA".',
            'revisao_interna_para_revisar' => fn ($r) => 'Esperando sua revisão' . ($r['feita_por'] ? " (feita por {$r['feita_por']})." : '.'),
            'atrasadas'     => fn ($r) => "Atrasada — aprovação era {$r['data_aprovacao']}.",
            'vencem_hoje'   => fn ($r) => 'Data de aprovação é hoje.',
            'reunioes_hoje' => fn ($r) => "Reunião às {$r['horario']}.",
            'outras_pendencias' => fn ($r) => $r['texto'] ?? 'Pendência aberta.',
        ];
        $title = fn ($r) => trim(($r['tarefa'] ?? $r['reuniao'] ?? $r['aviso'] ?? '') . (!empty($r['cliente']) ? ' — ' . $r['cliente'] : ''));

        $focus = [];
        foreach ($why as $group => $fn) {
            foreach ($items[$group]['itens'] ?? [] as $row) {
                $focus[] = ['ref' => $row['ref'], 'title' => $title($row), 'why' => $fn($row), 'url' => $links[$row['ref']]['url'], 'kind' => $links[$row['ref']]['kind']];
                if (count($focus) >= 5) {
                    return $focus;
                }
            }
        }
        return $focus;
    }
}

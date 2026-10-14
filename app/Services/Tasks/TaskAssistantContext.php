<?php

namespace App\Services\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * O que o Assistente de Lançamento enxerga do projeto pra poder REORGANIZAR tarefas que já
 * existem (pedido do Evandro, 2026-10-14): a lista inteira com datas/pessoas/status, cada
 * uma com uma referência curta (#1, #2...) que a IA usa nas alterações — o id de verdade
 * nunca sai do servidor, então ela não tem como apontar pra tarefa de outro projeto — e a
 * carga do time por dia, pra distribuir datas sem empilhar tudo em quem já está cheio.
 */
class TaskAssistantContext
{
    // Campos que o assistente pode alterar. Status/tipo/sprint ficam de fora de propósito:
    // status dispara aprovação, WhatsApp e automações — isso continua sendo feito por gente.
    public const EDITABLE = [
        'approval_date'       => 'Data de aprovação',
        'due_date'            => 'Vencimento',
        'publish_date'        => 'Data de publicação',
        'executor_user_id'    => 'Executor',
        'responsavel_user_id' => 'Responsável',
        'title'               => 'Título',
        'description'         => 'Descrição',
    ];

    public const DATE_FIELDS = ['approval_date', 'due_date', 'publish_date'];

    private const MAX_TASKS = 200;

    /**
     * @return array{0: string, 1: array<string, Task>} texto pra IA + mapa "#n" => tarefa
     */
    public static function existingTasks(Project $project): array
    {
        $tasks = $project->tasks()
            ->where('status', '!=', 'cancelado')
            ->with(['executors:id,name', 'sprint:id,title'])
            ->orderByRaw('approval_date asc nulls last')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::MAX_TASKS)
            ->get();

        if ($tasks->isEmpty()) {
            return ['Nenhuma tarefa criada ainda neste projeto.', []];
        }

        $refs  = [];
        $lines = [];
        foreach ($tasks->values() as $i => $task) {
            $ref = '#' . ($i + 1);
            $refs[$ref] = $task;

            $exec = self::person($task, 'executor');
            $resp = self::person($task, 'responsavel');
            $desc = trim((string) $task->description);

            $parts = [
                $ref,
                $task->title,
                $task->typeLabel(),
                $task->statusLabel() . ($task->situation ? " ({$task->situation})" : ''),
                'executor: ' . ($exec ? "{$exec->name} ({$exec->id})" : '—'),
                'responsável: ' . ($resp ? "{$resp->name} ({$resp->id})" : '—'),
                'aprovação: ' . self::dateLabel($task->approval_date),
                'vencimento: ' . self::dateLabel($task->due_date),
                'publicação: ' . self::dateLabel($task->publish_date),
                'sprint: ' . ($task->sprint ? \Illuminate\Support\Str::before($task->sprint->title, ' (') : '—'),
            ];
            if ($task->blocker_at) {
                $parts[] = 'TRAVA';
            }
            if ($desc !== '') {
                $parts[] = 'descrição: ' . \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $desc), 160);
            }
            $lines[] = implode(' | ', $parts);
        }

        $total = $project->tasks()->where('status', '!=', 'cancelado')->count();
        if ($total > self::MAX_TASKS) {
            $lines[] = "(mostrando {$tasks->count()} de {$total} tarefas — as demais não podem ser alteradas por aqui)";
        }

        return ["\n" . implode("\n", $lines), $refs];
    }

    /**
     * Carga de cada pessoa da equipe por dia útil, de hoje até $businessDays à frente: tarefas
     * abertas da fila de execução (Backlog/Produção/Ajuste — ExecutorQueue::STATUSES) de
     * OUTROS projetos (e avulsas), pela data de aprovação, mais as atrasadas. Mesma régua da
     * grade da Distribuição, só sem o recorte de sprint (aqui o assunto é data futura).
     * As tarefas do próprio projeto ficam de fora porque a IA já as vê na lista com a data —
     * contadas aqui também, ela somava duas vezes e achava que nada cabia (teste de 14/10).
     */
    public static function teamLoad(?string $exceptProjectId = null, int $businessDays = 12): string
    {
        $days = [];
        for ($d = today(); count($days) < $businessDays; $d = $d->copy()->addDay()) {
            if (! $d->isWeekend()) {
                $days[] = $d->copy();
            }
        }
        $end = end($days);

        $rows = ExecutorQueue::activeClients(Task::query())
            ->whereIn('tasks.status', ExecutorQueue::STATUSES)
            ->when($exceptProjectId, fn ($q) => $q->where(fn ($w) => $w->whereNull('tasks.project_id')->orWhere('tasks.project_id', '!=', $exceptProjectId)))
            ->whereNotNull('tasks.approval_date')
            ->whereDate('tasks.approval_date', '<=', $end)
            ->leftJoin('task_executors as te', fn ($j) => $j->on('te.task_id', '=', 'tasks.id')->where('te.role', 'executor'))
            ->selectRaw('coalesce(te.user_id, tasks.executor_id) as uid')
            ->selectRaw("case when tasks.approval_date < current_date then 'atrasadas' else to_char(tasks.approval_date, 'YYYY-MM-DD') end as dia")
            ->selectRaw('count(*) as n, sum(coalesce(tasks.sprint_points, 1)) as pts')
            ->groupByRaw('1, 2')
            ->toBase()->get()
            ->filter(fn ($r) => $r->uid)
            ->groupBy('uid');

        $users = User::whereNull('client_id')->orderBy('name')->get(['id', 'name']);

        $out = [];
        foreach ($users as $u) {
            $mine = collect($rows->get($u->id, []))->keyBy('dia');
            $cells = [];
            if ($late = $mine->get('atrasadas')) {
                $cells[] = "atrasadas {$late->n}";
            }
            foreach ($days as $d) {
                $r = $mine->get($d->toDateString());
                $cells[] = $d->format('d/m') . ' ' . self::weekday($d) . ': ' . ($r ? "{$r->n} ({$r->pts} pts)" : '0');
            }
            $out[] = "{$u->name} ({$u->id}): " . implode(' · ', $cells);
        }

        return "\n" . implode("\n", $out);
    }

    /**
     * Texto do documento HTML (planejamento ou campanha/projeto) pra IA ler — o HTML é o
     * ativo (2026-10: "o html será nosso ativo passível de leitura pela IA"). Tira script/
     * estilo/tags, mantém quebras de bloco. Cacheado por anexo (o arquivo não muda; anexo
     * novo = id novo), pra não baixar do R2 a cada mensagem.
     */
    public static function documentText(?\Illuminate\Database\Eloquent\Model $attachment, int $limit = 30000): ?string
    {
        if (!$attachment) {
            return null;
        }

        $text = \Illuminate\Support\Facades\Cache::remember(
            'assistant-doc:' . class_basename($attachment) . ':' . $attachment->id,
            now()->addDay(),
            function () use ($attachment) {
                try {
                    $html = \Illuminate\Support\Facades\Storage::disk($attachment->disk)->get($attachment->disk_path);
                } catch (\Throwable $e) {
                    return '';
                }
                $html = preg_replace('~<(script|style|svg|noscript)\b[^>]*>.*?</\1>~is', ' ', (string) $html);
                $html = preg_replace('~<(br|/p|/div|/li|/h[1-6]|/tr|/section)\b[^>]*>~i', "\n", $html);
                $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
                return trim(preg_replace("/\s*\n\s*/", "\n", $text));
            }
        );

        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > $limit
            ? mb_substr($text, 0, $limit) . "\n(documento cortado — mostrando o começo)"
            : $text;
    }

    /** Valor atual de um campo editável, no mesmo formato que a IA usa (data AAAA-MM-DD, id). */
    public static function currentValue(Task $task, string $field): mixed
    {
        return match ($field) {
            'executor_user_id'    => self::person($task, 'executor')?->id,
            'responsavel_user_id' => self::person($task, 'responsavel')?->id,
            'approval_date', 'due_date', 'publish_date' => $task->{$field}?->format('Y-m-d'),
            default => $task->{$field},
        };
    }

    public static function dateLabel(mixed $date): string
    {
        if (! $date) {
            return '—';
        }
        $d = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $d->format('d/m/Y') . ' (' . self::weekday($d) . ')';
    }

    /** Executor/Responsável vem do pivot; executor herdado (executor_id) só quando não há pivot. */
    private static function person(Task $task, string $role): ?User
    {
        $fromPivot = $task->executors->first(fn ($u) => $u->pivot->role === $role);
        if ($fromPivot || $role !== 'executor' || ! $task->executor_id) {
            return $fromPivot;
        }

        return User::find($task->executor_id, ['id', 'name']);
    }

    private static function weekday(Carbon $d): string
    {
        return ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'][$d->dayOfWeek];
    }
}

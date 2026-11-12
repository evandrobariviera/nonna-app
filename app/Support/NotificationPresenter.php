<?php

namespace App\Support;

use App\Models\InternalNotification;
use App\Models\MacroPlan;
use App\Models\Meeting;
use App\Models\Pendency;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Identidade visual dos avisos (Direção A, escolhida pelo Evandro em 12/11/2026) — uma regra
 * só pro sino e pro card de avisos da Dashboard:
 *   • BLOCO (o que fazer) pelo formato: "acao" = card laranja com um botão; "conversa" = rosto
 *     de quem falou + começo do texto; "saber" = linha discreta com o horário.
 *   • NATUREZA (do que se trata) pela cor do ícone — a mesma lei de cores do <x-item-card>
 *     (tarefa roxo, reunião azul, pendência ciano, planejamento índigo, campanha rosa, cliente cinza).
 *
 * Carrega a origem (cliente, tipo do projeto) e quem comentou em lote: uma consulta por tipo
 * de origem presente, nunca uma por aviso (o sino roda em toda página).
 */
class NotificationPresenter
{
    public const GROUPS = [
        'acao'     => 'Pede você',
        'conversa' => 'Conversas',
        'saber'    => 'Para saber',
    ];

    private const SOURCE_MODELS = [
        'Task'      => Task::class,
        'Project'   => Project::class,
        'Meeting'   => Meeting::class,
        'Pendency'  => Pendency::class,
        'MacroPlan' => MacroPlan::class,
    ];

    private const NATURE_LABELS = [
        'tarefa' => 'Tarefa', 'reuniao' => 'Reunião', 'pendencia' => 'Pendência',
        'planejamento' => 'Planejamento', 'campanha' => 'Campanha', 'projeto' => 'Projeto', 'cliente' => 'Cliente',
    ];

    public static function group(InternalNotification $n): string
    {
        return self::groupOfKind($n->kind);
    }

    public static function groupOfKind(?string $kind): string
    {
        return match (true) {
            in_array($kind, InternalNotification::CONVERSATION_KINDS, true) => 'conversa',
            in_array($kind, [...InternalNotification::INFO_KINDS, ...InternalNotification::AGENDA_KINDS], true) => 'saber',
            default => 'acao',
        };
    }

    /** kinds de um bloco que some ao abrir (pra "marcar como vistos"). */
    public static function kindsOf(string $group): array
    {
        return match ($group) {
            'conversa' => InternalNotification::CONVERSATION_KINDS,
            'saber'    => [...InternalNotification::INFO_KINDS, ...InternalNotification::AGENDA_KINDS],
            default    => [],
        };
    }

    /** @return Collection<int, object> um item por aviso, na mesma ordem */
    public static function present(Collection $notifications): Collection
    {
        if ($notifications->isEmpty()) {
            return collect();
        }

        $sources = [];
        foreach ($notifications->groupBy('source_type') as $type => $items) {
            $model = self::SOURCE_MODELS[$type] ?? null;
            if (! $model) {
                continue;
            }
            $with = in_array($type, ['Project'], true) ? ['client', 'macroPlan.client'] : ['client'];
            $sources[$type] = $model::with($with)->whereIn('id', $items->pluck('source_id')->filter()->unique())->get()->keyBy('id');
        }

        // Conversa: o título começa com o nome de quem comentou ("Vitor Pilatti comentou em …").
        $actorNames = $notifications
            ->filter(fn ($n) => self::group($n) === 'conversa')
            ->map(fn ($n) => self::actorName($n->title))
            ->filter()->unique()->values();
        $actors = $actorNames->isEmpty() ? collect() : User::whereIn('name', $actorNames)->get()->keyBy('name');

        return $notifications->map(function (InternalNotification $n) use ($sources, $actors) {
            $source = $sources[$n->source_type][$n->source_id] ?? null;
            $nature = self::nature($n, $source);
            $client = $source?->client ?? ($source instanceof Project ? $source->macroPlan?->client : null);
            $group = self::group($n);
            $actorName = $group === 'conversa' ? self::actorName($n->title) : null;

            return (object) [
                'n'           => $n,
                'group'       => $group,
                'nature'      => $nature === 'projeto' ? 'planejamento' : $nature, // cor do quadradinho
                'natureLabel' => self::NATURE_LABELS[$nature] ?? null,
                'icon'        => $n->kindIcon(),
                'client'      => $client?->displayName(),
                'actor'       => $actorName ? ($actors[$actorName] ?? null) : null,
                'actorName'   => $actorName,
                // Conversa: "Vitor comentou em Encarte" — o nome já aparece no rosto, o resto vira a frase.
                'headline'    => $actorName ? mb_substr($n->title, mb_strlen($actorName) + 1) : $n->title,
                'when'        => self::when($n),
            ];
        });
    }

    private static function nature(InternalNotification $n, $source): string
    {
        return match ($n->source_type) {
            'Task'      => 'tarefa',
            'Meeting'   => 'reuniao',
            'Pendency'  => 'pendencia',
            'MacroPlan' => 'planejamento',
            'Project'   => $source?->type === 'campanha' ? 'campanha' : 'projeto',
            default     => in_array($n->kind, ['oportunidade_ganha', 'oportunidade_perdida', 'cadastro_atualizado', 'portal_senha_definida', 'contrato_analise'], true)
                ? 'cliente' : 'tarefa',
        };
    }

    private static function actorName(string $title): ?string
    {
        foreach ([' comentou ', ' te citou '] as $verb) {
            if (($pos = mb_strpos($title, $verb)) !== false) {
                return mb_substr($title, 0, $pos);
            }
        }

        return null;
    }

    private static function when(InternalNotification $n): string
    {
        $at = $n->generated_at;
        $minutes = (int) $at->diffInMinutes(now());

        return match (true) {
            $minutes < 1     => 'agora',
            $minutes < 60    => "há {$minutes} min",
            $at->isToday()   => 'há ' . (int) $at->diffInHours(now()) . ' h',
            $at->isYesterday() => 'ontem',
            default          => $at->format('d/m'),
        };
    }
}

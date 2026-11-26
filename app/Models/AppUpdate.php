<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma entrada da linha do tempo de Novidades do App — ver a migration
 * create_app_updates_table pra diferença entre isto, notificação e Central de Ajuda.
 */
class AppUpdate extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'title', 'summary', 'body', 'kind', 'area',
        'published_at', 'created_by', 'is_featured',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured'  => 'boolean',
    ];

    /** Até quantos dias pra trás a janela olha quando a pessoa nunca abriu as Novidades. */
    public const POPUP_JANELA_DIAS = 14;

    /**
     * O que a janela do primeiro acesso do dia mostra pra essa pessoa — ou null se não
     * é pra abrir (já abriu hoje, é cliente, ou não tem nada novo). Abrir já marca o dia,
     * então ela aparece uma vez só mesmo que a pessoa troque de tela sem fechar.
     * Custo: fora a 1ª página do dia, é só a comparação de data no próprio usuário.
     *
     * @return array{destaques: \Illuminate\Support\Collection, outras: \Illuminate\Support\Collection, total: int}|null
     */
    public static function popupPara(?User $user): ?array
    {
        // Roda em toda tela: qualquer falha aqui (ex.: migration não aplicada) só esconde a janela.
        try {
            return static::montarPopup($user);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function montarPopup(?User $user): ?array
    {
        if (! $user || $user->isClient() || $user->app_updates_popup_on?->isToday()) {
            return null;
        }

        $user->forceFill(['app_updates_popup_on' => today()])->saveQuietly();

        $desde = $user->app_updates_seen_at ?? now()->subDays(self::POPUP_JANELA_DIAS);
        $base = static::publicadas()->whereRaw('greatest(published_at, created_at) > ?', [$desde]);

        $total = (clone $base)->count();
        if ($total === 0) {
            return null;
        }

        $itens = $base->orderByDesc('is_featured')->orderByRaw('greatest(published_at, created_at) desc')->limit(8)->get();

        return [
            'destaques' => $itens->where('is_featured', true)->values(),
            'outras'    => $itens->where('is_featured', false)->values(),
            'total'     => $total,
        ];
    }

    // Rótulo e cor por tipo. "Novidade" é coisa que não existia; "melhoria" mexe em algo
    // que já existia; "correção" conserta o que estava errado.
    public static array $kinds = [
        'novidade'  => ['label' => 'Novidade', 'color' => 'var(--purple)'],
        'melhoria'  => ['label' => 'Melhoria', 'color' => 'var(--orange)'],
        'correcao'  => ['label' => 'Correção', 'color' => 'var(--green)'],
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** O que a equipe vê: publicado e com data já passada. */
    public function scopePublicadas(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function kindLabel(): string
    {
        return static::$kinds[$this->kind]['label'] ?? $this->kind;
    }

    public function kindColor(): string
    {
        return static::$kinds[$this->kind]['color'] ?? 'var(--muted)';
    }

    /**
     * Quando a novidade chegou pra equipe: a publicação, ou o cadastro se foi depois. Uma
     * novidade registrada hoje com data de entrega de semanas atrás (published_at no
     * passado) ainda é nova pra quem abriu a tela ontem.
     */
    public function chegouEm(): ?\Illuminate\Support\Carbon
    {
        return $this->published_at?->max($this->created_at);
    }

    /** Quantas novidades essa pessoa ainda não viu — alimenta o contador na barra lateral. */
    public static function naoVistasPor(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return static::publicadas()
            ->when($user->app_updates_seen_at, fn ($q) => $q->whereRaw('greatest(published_at, created_at) > ?', [$user->app_updates_seen_at]))
            ->count();
    }
}

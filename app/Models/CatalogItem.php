<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Item do catálogo de Produtos e Serviços (Central de Comércio → Propostas).
 * Ver migration 2026_11_11_100000_create_catalog_tables pra semântica dos campos.
 *
 * rules (JSON), conforme o tipo:
 *  - pacote fechado (PlaNo C): ads_per_month, videos_per_month, concurrent_campaigns,
 *    ad_budget_cap, notified_attendants (null = sem limite), excess_rate, upgrade_to (id),
 *    recommended, includes (lista de textos que o cliente vê)
 *  - item: requires_closed_package, not_in_packages (ids), not_in_reason,
 *    effects {ads, videos, campaigns, attendants}, yields_videos (captação)
 */
class CatalogItem extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'code',
        'line',
        'kind',
        'package_type',
        'name',
        'description',
        'monthly_price',
        'one_time_price',
        'price_is_starting',
        'value_status',
        'rules',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price'     => 'decimal:2',
        'one_time_price'    => 'decimal:2',
        'price_is_starting' => 'boolean',
        'rules'             => 'array',
        'is_active'         => 'boolean',
        'sort_order'        => 'integer',
    ];

    public static array $kinds = [
        'package'  => ['label' => 'Pacote',              'short' => 'pacote', 'color' => 'purple'],
        'monthly'  => ['label' => 'Serviço mensal',      'short' => 'mensal', 'color' => 'blue'],
        'one_time' => ['label' => 'Produto ou projeto',  'short' => 'único',  'color' => 'orange'],
    ];

    public static array $packageTypes = [
        'closed' => ['label' => 'Fechado', 'hint' => 'Como o PlaNo C: tem volumes e regras, e na proposta só dá pra somar itens'],
        'open'   => ['label' => 'Aberto',  'hint' => 'Montado pela Nonna a partir de outros itens, com preço próprio'],
    ];

    public static array $valueStatuses = [
        'decided'   => ['label' => 'decidido', 'color' => 'green'],
        'suggested' => ['label' => 'sugerido', 'color' => 'yellow'],
    ];

    /** Linhas sugeridas no formulário — a linha é texto livre, então dá pra criar outras. */
    public static array $defaultLines = ['PlaNo C', 'Web', 'Produtora', 'Mídia', 'OnMap'];

    // ── Relacionamentos ──

    /** Itens que compõem um pacote aberto. */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'catalog_package_items', 'package_id', 'item_id')
            ->using(CatalogPackageItem::class)
            ->withPivot('id', 'quantity')
            ->withTimestamps()
            ->orderBy('catalog_items.sort_order');
    }

    // ── Scopes ──

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    // ── Helpers ──

    public function isPackage(): bool
    {
        return $this->kind === 'package';
    }

    public function isClosedPackage(): bool
    {
        return $this->isPackage() && $this->package_type === 'closed';
    }

    public function isOpenPackage(): bool
    {
        return $this->isPackage() && $this->package_type === 'open';
    }

    public function rule(string $key, mixed $default = null): mixed
    {
        return data_get($this->rules, $key, $default);
    }

    public function kindLabel(): string
    {
        if ($this->isPackage()) {
            return 'Pacote ' . mb_strtolower(self::$packageTypes[$this->package_type]['label'] ?? '');
        }
        return self::$kinds[$this->kind]['label'] ?? $this->kind;
    }

    public function kindColor(): string
    {
        return self::$kinds[$this->kind]['color'] ?? 'muted';
    }

    public function valueStatusLabel(): string
    {
        return self::$valueStatuses[$this->value_status]['label'] ?? $this->value_status;
    }

    public function valueStatusColor(): string
    {
        return self::$valueStatuses[$this->value_status]['color'] ?? 'muted';
    }

    /** Texto do preço pra listas: "R$ 2.500,00/mês + R$ 1.500,00", "a partir de R$ 900,00". */
    public function priceLabel(): string
    {
        $fmt = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
        $parts = [];
        if ($this->monthly_price !== null && (float) $this->monthly_price > 0) {
            $parts[] = $fmt($this->monthly_price) . '/mês';
        }
        if ($this->one_time_price !== null && (float) $this->one_time_price > 0) {
            $parts[] = $fmt($this->one_time_price) . ($this->isPackage() && $parts ? ' de implantação' : '');
        }
        $label = $parts ? implode(' + ', $parts) : '—';
        return $this->price_is_starting ? 'a partir de ' . $label : $label;
    }
}

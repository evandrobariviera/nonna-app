<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Services\Catalog\CatalogItemWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Central de Comércio → Produtos e serviços: o catálogo de preços que as Propostas usam.
 * Todo mundo vê; só admin/dono edita (rotas de escrita no grupo org.admin).
 */
class CatalogItemController extends Controller
{
    public function index(Request $request): View
    {
        $kind = $request->get('tipo');
        $line = $request->get('linha');

        $all = CatalogItem::with('components:id,name,kind,monthly_price,one_time_price')->ordered()->get();

        $items = $all
            ->when(isset(CatalogItem::$kinds[$kind]), fn ($c) => $c->where('kind', $kind))
            ->when($line, fn ($c) => $c->where('line', $line))
            ->when(!$request->boolean('inativos'), fn ($c) => $c->where('is_active', true));

        $lines = $all->pluck('line')->merge(CatalogItem::$defaultLines)->unique()->values();
        $closedPackages = $all->filter->isClosedPackage()->values();
        $componentOptions = $all->where('kind', '!=', 'package')->values();

        return view('catalog.index', compact('items', 'lines', 'closedPackages', 'componentOptions', 'kind', 'line'));
    }

    public function store(Request $request, CatalogItemWriter $writer): RedirectResponse
    {
        $item = $writer->save(new CatalogItem(), $this->validated($request));

        return redirect()->route('catalog.index')->with('success', "\"{$item->name}\" entrou no catálogo.");
    }

    public function update(Request $request, CatalogItem $catalogItem, CatalogItemWriter $writer): RedirectResponse
    {
        $writer->save($catalogItem, $this->validated($request, $catalogItem));

        return redirect()->back()->with('success', "\"{$catalogItem->name}\" atualizado.");
    }

    public function destroy(CatalogItem $catalogItem): RedirectResponse
    {
        $usedInPackage = $catalogItem->belongsToMany(CatalogItem::class, 'catalog_package_items', 'item_id', 'package_id')->exists();
        $isUpgradeTarget = CatalogItem::where('rules->upgrade_to', $catalogItem->id)->exists();

        if ($usedInPackage || $isUpgradeTarget) {
            return redirect()->back()->with('error', "\"{$catalogItem->name}\" faz parte de um pacote ou é o nível de cima de outro. Desative em vez de excluir.");
        }

        $name = $catalogItem->name;
        $catalogItem->delete();

        return redirect()->route('catalog.index')->with('success', "\"{$name}\" excluído do catálogo.");
    }

    private function validated(Request $request, ?CatalogItem $item = null): array
    {
        return $request->validate([
            'name'                    => 'required|string|max:160',
            'line'                    => 'required|string|max:60',
            'kind'                    => ['required', Rule::in(array_keys(CatalogItem::$kinds))],
            'package_type'            => ['nullable', Rule::in(array_keys(CatalogItem::$packageTypes))],
            'description'             => 'nullable|string|max:1000',
            'monthly_price'           => 'nullable|numeric|min:0|max:9999999',
            'one_time_price'          => 'nullable|numeric|min:0|max:9999999',
            'price_is_starting'       => 'nullable|boolean',
            'value_status'            => ['required', Rule::in(array_keys(CatalogItem::$valueStatuses))],
            'is_active'               => 'nullable|boolean',
            // pacote fechado
            'ads_per_month'           => 'nullable|integer|min:0|max:999',
            'videos_per_month'        => 'nullable|integer|min:0|max:999',
            'concurrent_campaigns'    => 'nullable|integer|min:0|max:99',
            'ad_budget_cap'           => 'nullable|numeric|min:0',
            'notified_attendants'     => 'nullable|integer|min:0|max:999',
            'excess_rate'             => 'nullable|numeric|min:0|max:100',
            'upgrade_to'              => ['nullable', 'uuid', Rule::notIn(array_filter([$item?->id]))],
            'recommended'             => 'nullable|boolean',
            'includes'                => 'nullable|string|max:3000',
            // pacote aberto
            'components'              => 'nullable|array',
            'components.*.item_id'    => 'nullable|uuid',
            'components.*.quantity'   => 'nullable|integer|min:1|max:999',
            // item
            'requires_closed_package' => 'nullable|boolean',
            'not_in_packages'         => 'nullable|array',
            'not_in_packages.*'       => 'uuid',
            'not_in_reason'           => 'nullable|string|max:200',
            'effect_ads'              => 'nullable|integer|min:0|max:999',
            'effect_videos'           => 'nullable|integer|min:0|max:999',
            'effect_campaigns'        => 'nullable|integer|min:0|max:99',
            'effect_attendants'       => 'nullable|integer|min:0|max:999',
            'yields_videos'           => 'nullable|integer|min:0|max:99',
        ], [], [
            'name' => 'nome', 'line' => 'linha', 'monthly_price' => 'preço mensal', 'one_time_price' => 'preço único',
        ]);
    }
}

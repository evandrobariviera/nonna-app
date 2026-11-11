<?php

namespace App\Services\Catalog;

use App\Models\CatalogItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Grava um item do catálogo a partir do formulário (CatalogItemController) — monta o JSON
 * de regras conforme o tipo e sincroniza a composição dos pacotes abertos.
 * Campos de regra que não fazem sentido pro tipo escolhido são descartados.
 */
class CatalogItemWriter
{
    public function save(CatalogItem $item, array $data): CatalogItem
    {
        $kind = $data['kind'];
        $packageType = $kind === 'package' ? ($data['package_type'] ?? 'open') : null;

        $item->fill([
            'name'              => trim($data['name']),
            'line'              => trim($data['line']),
            'kind'              => $kind,
            'package_type'      => $packageType,
            'description'       => $data['description'] ?? null,
            'monthly_price'     => $kind === 'one_time' ? null : ($data['monthly_price'] ?? null),
            'one_time_price'    => $kind === 'monthly' ? null : ($data['one_time_price'] ?? null),
            'price_is_starting' => (bool) ($data['price_is_starting'] ?? false),
            'value_status'      => $data['value_status'],
            'is_active'         => (bool) ($data['is_active'] ?? false),
            'rules'             => $this->rules($kind, $packageType, $data),
        ]);
        if (!$item->exists) {
            $item->sort_order = (int) CatalogItem::max('sort_order') + 1;
        }

        return DB::transaction(function () use ($item, $packageType, $data) {
            $item->save();

            if ($packageType === 'open') {
                $sync = [];
                foreach ($data['components'] ?? [] as $row) {
                    if (empty($row['item_id']) || $row['item_id'] === $item->id) {
                        continue;
                    }
                    $sync[$row['item_id']] = ['quantity' => max(1, (int) ($row['quantity'] ?? 1))];
                }
                $nested = CatalogItem::whereIn('id', array_keys($sync))->where('kind', 'package')->exists();
                if ($nested) {
                    throw ValidationException::withMessages(['components' => 'Um pacote não pode conter outro pacote.']);
                }
                $item->components()->sync($sync);
            } else {
                $item->components()->detach();
            }

            return $item;
        });
    }

    private function rules(string $kind, ?string $packageType, array $data): ?array
    {
        $int = fn ($k) => isset($data[$k]) && $data[$k] !== '' ? (int) $data[$k] : null;

        if ($packageType === 'closed') {
            return [
                'ads_per_month'        => $int('ads_per_month') ?? 0,
                'videos_per_month'     => $int('videos_per_month') ?? 0,
                'concurrent_campaigns' => $int('concurrent_campaigns') ?? 0,
                'ad_budget_cap'        => isset($data['ad_budget_cap']) && $data['ad_budget_cap'] !== '' ? (float) $data['ad_budget_cap'] : null,
                'notified_attendants'  => $int('notified_attendants'),
                'excess_rate'          => round(((float) ($data['excess_rate'] ?? 10)) / 100, 4),
                'upgrade_to'           => $data['upgrade_to'] ?: null,
                'recommended'          => (bool) ($data['recommended'] ?? false),
                'includes'             => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['includes'] ?? ''))))),
            ];
        }

        if ($kind === 'package') {
            return null;
        }

        $effects = array_filter([
            'ads'        => $int('effect_ads'),
            'videos'     => $int('effect_videos'),
            'campaigns'  => $int('effect_campaigns'),
            'attendants' => $int('effect_attendants'),
        ]);
        $rules = array_filter([
            'requires_closed_package' => (bool) ($data['requires_closed_package'] ?? false),
            'not_in_packages'         => array_values($data['not_in_packages'] ?? []),
            'not_in_reason'           => !empty($data['not_in_packages']) ? ($data['not_in_reason'] ?? null) : null,
            'effects'                 => $effects,
            'yields_videos'           => $int('yields_videos'),
        ]);

        return $rules ?: null;
    }
}

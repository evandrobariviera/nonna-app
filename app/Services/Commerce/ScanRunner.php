<?php

namespace App\Services\Commerce;

use App\Models\CommercePlace;
use App\Models\CommerceScan;
use Illuminate\Support\Facades\DB;

/**
 * Executa uma varredura: roda cada termo do segmento (e o nicho) na cidade, junta os IDs
 * sem repetir e grava as empresas (só place_id). Termo que volta cheio (60) marca a
 * varredura como "saturada": pode haver mais empresas do que o Google devolve numa busca.
 */
class ScanRunner
{
    public function __construct(private ?GooglePlaces $places = null) {}

    /** @return string[] buscas que a varredura vai rodar */
    public static function queries(CommerceScan $scan): array
    {
        $where = trim($scan->city->name . ' ' . $scan->city->state);
        $terms = $scan->niche ? [$scan->niche] : (array) ($scan->segment?->search_terms ?? []);

        return array_values(array_unique(array_filter(array_map(
            fn ($t) => trim((string) $t) !== '' ? trim((string) $t) . ' em ' . $where : null,
            $terms
        ))));
    }

    public function run(CommerceScan $scan): CommerceScan
    {
        $scan->loadMissing('city', 'segment');
        $places = $this->places ?? GooglePlaces::forOrganization($scan->organization_id);
        $scan->update(['status' => 'running', 'error' => null]);

        try {
            // Nicho é texto livre: sem filtro de tipo (o recorte pode não bater com o tipo do Google).
            $type = $scan->niche ? null : $scan->segment?->google_type;
            $terms = [];
            $ids = [];
            $calls = 0;
            foreach (self::queries($scan) as $query) {
                $res = $places->searchIds($query, $type);
                $calls += $res['calls'];
                $terms[] = ['term' => $query, 'found' => count($res['ids']), 'saturated' => $res['saturated']];
                $ids = array_merge($ids, $res['ids']);
            }
            $ids = array_values(array_unique($ids));

            $new = DB::connection('pgsql')->transaction(fn () => $this->store($scan, $ids));

            $scan->update([
                'status'       => 'done',
                'terms'        => $terms,
                'places_found' => count($ids),
                'places_new'   => $new,
                'saturated'    => collect($terms)->contains('saturated', true),
                'api_calls'    => $calls,
                'finished_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            $scan->update(['status' => 'error', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
        }

        return $scan->fresh();
    }

    /** @return int quantas eram novas */
    private function store(CommerceScan $scan, array $ids): int
    {
        if (!$ids) {
            return 0;
        }
        $existing = CommercePlace::withoutGlobalScopes()->where('organization_id', $scan->organization_id)
            ->whereIn('place_id', $ids)->pluck('id', 'place_id');
        $now = now();
        $attach = [];
        $new = 0;

        foreach ($ids as $pid) {
            if (isset($existing[$pid])) {
                $attach[] = $existing[$pid];
                continue;
            }
            $place = CommercePlace::create([
                'organization_id'     => $scan->organization_id,
                'place_id'            => $pid,
                'city_id'             => $scan->city_id,
                'segment_id'          => $scan->segment_id,
                'status'              => 'mapped',
                'last_seen_at'        => $now,
                'place_id_checked_at' => $now,
            ]);
            $attach[] = $place->id;
            $new++;
        }

        CommercePlace::withoutGlobalScopes()->whereIn('id', $attach)->update(['last_seen_at' => $now, 'place_id_checked_at' => $now]);
        $scan->places()->syncWithoutDetaching($attach);

        return $new;
    }
}

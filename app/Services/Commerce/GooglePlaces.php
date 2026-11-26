<?php

namespace App\Services\Commerce;

use App\Models\OrganizationIntegration;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

/**
 * Google Places API (New) para a prospecção da Central de Comércio.
 *
 * Custo e termos (ver .claude/docs/central-comercio.md):
 * - searchIds(): Text Search pedindo SÓ o ID (field mask places.id) = SKU
 *   "Text Search Essentials (IDs Only)", gratuito e sem limite. É o que a varredura usa.
 * - details(): Place Details com nome/endereço = SKU Pro (cobrado por empresa). Só para
 *   as empresas que estão na tela, na hora. O retorno NUNCA vai para o banco.
 *
 * A chave fica em Configurações → Integrações → "Google Maps (Places)" (provider
 * google_maps, campo access_token), de um projeto do Google Cloud só da prospecção.
 */
class GooglePlaces
{
    public const BASE = 'https://places.googleapis.com/v1';

    /** O Google devolve no máximo 3 páginas de 20 por busca. */
    public const MAX_PAGES = 3;
    public const PAGE_SIZE = 20;

    public const DETAIL_FIELDS = 'id,displayName,shortFormattedAddress,primaryTypeDisplayName,googleMapsUri,businessStatus';

    /** Liberado por admin ao pedir coleta acima da franquia (GoogleUsage). Sem isso, nada pago. */
    private bool $allowPaid = false;

    public function __construct(private ?string $apiKey = null, private ?string $organizationId = null) {}

    public static function forOrganization(string $organizationId): self
    {
        $integration = OrganizationIntegration::withoutGlobalScopes()
            ->where('organization_id', $organizationId)->where('provider', 'google_maps')->where('status', 'connected')->first();

        return new self($integration?->credential('access_token'), $organizationId);
    }

    public function allowPaid(bool $allow = true): self
    {
        $this->allowPaid = $allow;

        return $this;
    }

    private function record(string $sku, int $calls): void
    {
        if ($this->organizationId) {
            GoogleUsage::record($this->organizationId, $sku, $calls);
        }
    }

    /** Quantas chamadas desse tipo ainda pode fazer sem gerar custo (null = sem limite). */
    private function allowedCalls(string $sku): ?int
    {
        if ($this->allowPaid || !$this->organizationId) {
            return null;
        }

        return GoogleUsage::freeLeft($this->organizationId, $sku);
    }

    public function configured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * IDs das empresas de uma busca (até 60). Gratuito.
     *
     * @return array{ids: string[], saturated: bool, calls: int}
     */
    public function searchIds(string $query, ?string $includedType = null, ?array $viewport = null): array
    {
        $this->ensureConfigured();
        $ids = [];
        $token = null;
        $calls = 0;
        $pages = 0;

        do {
            $body = array_filter([
                'textQuery'    => $query,
                'includedType' => $includedType ?: null,
                // Prende a busca dentro dos limites da cidade (CommerceCity::viewport).
                'locationRestriction' => $viewport ? ['rectangle' => ['low' => $viewport['low'], 'high' => $viewport['high']]] : null,
                'languageCode' => 'pt-BR',
                'regionCode'   => 'BR',
                'pageSize'     => self::PAGE_SIZE,
                'pageToken'    => $token,
            ]);

            $response = Http::timeout(20)
                ->withHeaders(['X-Goog-Api-Key' => $this->apiKey, 'X-Goog-FieldMask' => 'places.id,nextPageToken'])
                ->post(self::BASE . '/places:searchText', $body);
            $calls++;

            if (!$response->successful()) {
                throw new \RuntimeException('Google Places recusou a busca: ' . ($response->json('error.message') ?? 'HTTP ' . $response->status()));
            }

            foreach ((array) $response->json('places', []) as $place) {
                if (!empty($place['id'])) {
                    $ids[] = (string) $place['id'];
                }
            }
            $pages++;
            $token = $response->json('nextPageToken');
        } while ($token && $pages < self::MAX_PAGES);

        $this->record('busca', $calls); // grátis, mas fica no medidor
        $ids = array_values(array_unique($ids));

        // Voltou cheio (3 páginas e ainda havia próxima, ou 60 resultados): pode haver mais empresas.
        return ['ids' => $ids, 'saturated' => count($ids) >= self::MAX_PAGES * self::PAGE_SIZE || ($pages >= self::MAX_PAGES && $token), 'calls' => $calls];
    }

    /**
     * Nome, endereço e link do Maps, ao vivo, para mostrar na tela. Não guardar.
     *
     * @param  string[] $placeIds
     * @return array<string, array{name: ?string, address: ?string, type: ?string, maps_url: ?string, closed: bool}>
     */
    public function details(array $placeIds): array
    {
        $placeIds = array_values(array_unique(array_filter($placeIds)));
        if (!$placeIds || !$this->configured()) {
            return [];
        }

        // Automático (abrir a tela): só o que cabe na franquia grátis; o resto fica sem nome.
        $allowed = $this->allowedCalls('lista');
        if ($allowed !== null) {
            $placeIds = array_slice($placeIds, 0, $allowed);
            if (!$placeIds) {
                return [];
            }
        }
        $this->record('lista', count($placeIds));

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn ($id) => $pool->as($id)->timeout(15)
                ->withHeaders(['X-Goog-Api-Key' => $this->apiKey, 'X-Goog-FieldMask' => self::DETAIL_FIELDS])
                ->get(self::BASE . '/places/' . rawurlencode($id), ['languageCode' => 'pt-BR', 'regionCode' => 'BR']),
            $placeIds
        ));

        $out = [];
        foreach ($placeIds as $id) {
            $r = $responses[$id] ?? null;
            if (!$r instanceof \Illuminate\Http\Client\Response || !$r->successful()) {
                continue;
            }
            $out[$id] = [
                'name'     => $r->json('displayName.text'),
                'address'  => $r->json('shortFormattedAddress'),
                'type'     => $r->json('primaryTypeDisplayName.text'),
                'maps_url' => $r->json('googleMapsUri'),
                'closed'   => in_array($r->json('businessStatus'), ['CLOSED_PERMANENTLY', 'CLOSED_TEMPORARILY'], true),
            ];
        }

        return $out;
    }

    /** Campos da coleta (SKU Enterprise, ~1 chamada por empresa): o que a Nota Nonna precisa. */
    public const COLLECT_FIELDS = 'id,displayName,formattedAddress,shortFormattedAddress,primaryType,primaryTypeDisplayName,nationalPhoneNumber,websiteUri,regularOpeningHours,rating,userRatingCount,photos,googleMapsUri,businessStatus';

    /**
     * Dados de uma empresa para calcular a nota e mostrar o raio-x. Ao vivo, NUNCA guardar
     * (só os pontos calculados a partir deles, que são da Nonna).
     *
     * @return array{name: ?string, address: ?string, type: ?string, primary_type: ?string, phone: ?string, website: ?string,
     *               has_hours: bool, rating: ?float, reviews: int, photos: int, maps_url: ?string, closed: bool}|null
     */
    public function collect(string $placeId): ?array
    {
        $this->ensureConfigured();
        if ($this->allowedCalls('coleta') === 0) {
            throw new FreeTierExhausted('coleta');
        }
        $this->record('coleta', 1);
        $r = Http::timeout(20)
            ->withHeaders(['X-Goog-Api-Key' => $this->apiKey, 'X-Goog-FieldMask' => self::COLLECT_FIELDS])
            ->get(self::BASE . '/places/' . rawurlencode($placeId), ['languageCode' => 'pt-BR', 'regionCode' => 'BR']);

        if ($r->status() === 404) {
            return null; // empresa saiu do Google (ou o ID mudou)
        }
        if (!$r->successful()) {
            throw new \RuntimeException('Google Places recusou a coleta: ' . ($r->json('error.message') ?? 'HTTP ' . $r->status()));
        }

        return [
            'name'         => $r->json('displayName.text'),
            'address'      => $r->json('formattedAddress'),
            'short_address' => $r->json('shortFormattedAddress'),
            'type'         => $r->json('primaryTypeDisplayName.text'),
            'primary_type' => $r->json('primaryType'),
            'phone'        => $r->json('nationalPhoneNumber'),
            'website'      => $r->json('websiteUri'),
            'has_hours'    => !empty($r->json('regularOpeningHours.periods')) || !empty($r->json('regularOpeningHours.weekdayDescriptions')),
            'rating'       => $r->json('rating') !== null ? (float) $r->json('rating') : null,
            'reviews'      => (int) $r->json('userRatingCount', 0),
            'photos'       => count((array) $r->json('photos', [])), // a API devolve no máximo 10
            'maps_url'     => $r->json('googleMapsUri'),
            'closed'       => in_array($r->json('businessStatus'), ['CLOSED_PERMANENTLY', 'CLOSED_TEMPORARILY'], true),
        ];
    }

    public function apiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * Acha a cidade no Google e devolve os limites no mapa. Tenta o município inteiro
     * (administrative_area_level_2) e, se o Google não tiver, a localidade (área urbana) —
     * cada cidade está cadastrada de um jeito lá. Confere UF e nome: pedir "Tangará, Mato
     * Grosso" devolve "Tangará da Serra", e isso não pode passar. 1-2 chamadas por cidade.
     *
     * @return array{google_place_id: string, google_label: string, viewport: array}
     */
    public function locateCity(string $name, string $uf): array
    {
        $this->ensureConfigured();
        $uf = strtoupper($uf);
        $query = $name . ', ' . (\App\Models\CommerceCity::UFS[$uf] ?? $uf) . ', Brasil';
        $norm = fn (?string $s) => \Illuminate\Support\Str::of((string) $s)->ascii()->lower()->squish()->value();
        $found = [];

        foreach (['administrative_area_level_2', 'locality'] as $type) {
            $response = Http::timeout(20)
                ->withHeaders(['X-Goog-Api-Key' => $this->apiKey, 'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.viewport,places.addressComponents'])
                ->post(self::BASE . '/places:searchText', [
                    'textQuery' => $query, 'includedType' => $type, 'strictTypeFiltering' => true,
                    'languageCode' => 'pt-BR', 'regionCode' => 'BR', 'pageSize' => 5,
                ]);
            $this->record('cidade', 1);

            if (!$response->successful()) {
                throw new \RuntimeException('Google Places recusou a busca da cidade: ' . ($response->json('error.message') ?? 'HTTP ' . $response->status()));
            }

            foreach ((array) $response->json('places', []) as $p) {
                $placeUf = collect($p['addressComponents'] ?? [])->first(fn ($c) => in_array('administrative_area_level_1', $c['types'] ?? [], true))['shortText'] ?? null;
                $found[] = $p['formattedAddress'] ?? ($p['displayName']['text'] ?? '');
                if (strtoupper((string) $placeUf) === $uf && $norm($p['displayName']['text'] ?? '') === $norm($name) && !empty($p['viewport']['low'])) {
                    return ['google_place_id' => $p['id'], 'google_label' => $p['formattedAddress'] ?? "{$name} - {$uf}", 'viewport' => $p['viewport']];
                }
            }
        }

        $found = array_values(array_unique(array_filter($found)));
        throw new \RuntimeException("Não achei {$name} - {$uf} no Google." . ($found ? ' O Google sugeriu: ' . implode('; ', $found) . '. Confira o nome (com acento) e a UF.' : ' Confira o nome (com acento) e a UF.'));
    }

    private function ensureConfigured(): void
    {
        if (!$this->configured()) {
            throw new \RuntimeException('Falta a chave do Google Maps (Places): Configurações → Integrações → Google Maps (Places).');
        }
    }
}

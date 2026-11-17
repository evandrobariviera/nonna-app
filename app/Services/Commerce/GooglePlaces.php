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

    public function __construct(private ?string $apiKey = null) {}

    public static function forOrganization(string $organizationId): self
    {
        $integration = OrganizationIntegration::withoutGlobalScopes()
            ->where('organization_id', $organizationId)->where('provider', 'google_maps')->where('status', 'connected')->first();

        return new self($integration?->credential('access_token'));
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
    public function searchIds(string $query, ?string $includedType = null): array
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

    private function ensureConfigured(): void
    {
        if (!$this->configured()) {
            throw new \RuntimeException('Falta a chave do Google Maps (Places): Configurações → Integrações → Google Maps (Places).');
        }
    }
}

<?php

namespace App\Services\Commerce;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trava de custo do Google Places (Evandro, 25/11/2026: "saber quando vai começar a usar
 * crédito de API antes de pedir a raspagem").
 *
 * Conta cada chamada por mês e por tipo, compara com a franquia grátis do Google e:
 *  - chamada AUTOMÁTICA (nome/endereço na tela, dados ao vivo na página da empresa) para
 *    sozinha quando a franquia do mês acaba — nunca gera custo sem alguém pedir;
 *  - COLETA pedida por botão: se passar da franquia, o App mostra quantas seriam pagas e o
 *    custo estimado, e só segue com a confirmação de um admin/dono (allowPaid).
 *
 * Franquia e preço: tabela do Google Maps Platform (preço por SKU desde 03/2025). Conferir em
 * https://developers.google.com/maps/billing-and-pricing/pricing se o Google mudar a tabela.
 * O mês é o do calendário (o Google zera no dia 1, horário do Pacífico: diferença de horas).
 */
class GoogleUsage
{
    public const SKUS = [
        'busca'  => ['label' => 'Varredura (Text Search, só IDs)',           'free' => null, 'usd_per_1000' => 0.0],
        'lista'  => ['label' => 'Nome e endereço na tela (Place Details Pro)', 'free' => 5000, 'usd_per_1000' => 17.0],
        'coleta' => ['label' => 'Coleta da Nota Nonna (Place Details Enterprise)', 'free' => 1000, 'usd_per_1000' => 20.0],
    ];

    public static function month(): string
    {
        return now()->format('Y-m');
    }

    public static function record(string $organizationId, string $sku, int $calls): void
    {
        if ($calls <= 0) {
            return;
        }
        DB::connection('pgsql')->statement(
            'insert into google_places_usage (id, organization_id, month, sku, calls, created_at, updated_at)
             values (?, ?, ?, ?, ?, now(), now())
             on conflict (organization_id, month, sku) do update set calls = google_places_usage.calls + excluded.calls, updated_at = now()',
            [(string) Str::uuid(), $organizationId, self::month(), $sku, $calls]
        );
    }

    public static function used(string $organizationId, string $sku): int
    {
        return (int) DB::connection('pgsql')->table('google_places_usage')
            ->where('organization_id', $organizationId)->where('month', self::month())->where('sku', $sku)->value('calls');
    }

    /** Quantas chamadas grátis ainda restam no mês (null = sem limite). */
    public static function freeLeft(string $organizationId, string $sku): ?int
    {
        $free = self::SKUS[$sku]['free'];

        return $free === null ? null : max(0, $free - self::used($organizationId, $sku));
    }

    /** Quantas de $n chamadas seriam pagas, e o custo estimado em US$. */
    public static function estimate(string $organizationId, string $sku, int $n): array
    {
        $left = self::freeLeft($organizationId, $sku);
        $paid = $left === null ? 0 : max(0, $n - $left);

        return ['paid' => $paid, 'free_left' => $left, 'usd' => round($paid * self::SKUS[$sku]['usd_per_1000'] / 1000, 2)];
    }

    /** Resumo do mês para o painel da Prospecção. */
    public static function summary(string $organizationId): array
    {
        $rows = DB::connection('pgsql')->table('google_places_usage')
            ->where('organization_id', $organizationId)->where('month', self::month())->pluck('calls', 'sku');

        return collect(self::SKUS)->map(fn ($meta, $sku) => $meta + [
            'used' => (int) ($rows[$sku] ?? 0),
            'left' => $meta['free'] === null ? null : max(0, $meta['free'] - (int) ($rows[$sku] ?? 0)),
        ])->all();
    }
}

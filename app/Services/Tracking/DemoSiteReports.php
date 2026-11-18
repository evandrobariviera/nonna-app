<?php

namespace App\Services\Tracking;

use Carbon\Carbon;

/**
 * Números fictícios de Google Analytics e Search Console para a organização de
 * demonstração (Portal de exemplo). Mesmo formato do GoogleSiteReports, sem chamar o
 * Google. Os números de cada dia são sempre os mesmos (semente pela data), então a tela
 * não muda a cada recarga e os totais batem com o gráfico.
 */
class DemoSiteReports
{
    private const CHANNELS = [
        ['Organic Search', 0.34, 0.030],
        ['Paid Social', 0.22, 0.034],
        ['Paid Search', 0.17, 0.052],
        ['Direct', 0.13, 0.018],
        ['Organic Social', 0.07, 0.010],
        ['Referral', 0.04, 0.020],
        ['Display', 0.02, 0.006],
        ['Email', 0.01, 0.040],
    ];

    private const PAGES = [
        ['/', 0.31, 0.010], ['/orcamento', 0.21, 0.105], ['/galpoes-industriais', 0.14, 0.022],
        ['/mezaninos', 0.09, 0.028], ['/obras-entregues', 0.08, 0.012], ['/coberturas', 0.05, 0.016],
        ['/catalogo-tecnico', 0.04, 0.060], ['/blog/estrutura-metalica-ou-concreto', 0.04, 0.004],
        ['/sobre', 0.02, 0.0], ['/trabalhe-conosco', 0.02, 0.0],
    ];

    private const QUERIES = [
        ['aurora estruturas metálicas', 0.21, 1.2, 0.38], ['galpão metálico preço', 0.12, 6.8, 0.05],
        ['estrutura metálica chapecó', 0.11, 2.4, 0.11], ['mezanino metálico', 0.09, 5.3, 0.06],
        ['galpão pré-moldado ou metálico', 0.07, 8.9, 0.03], ['empresa de galpão em sc', 0.07, 4.1, 0.07],
        ['cobertura termoacústica', 0.05, 11.2, 0.02], ['quanto custa um galpão de 1000m2', 0.05, 9.6, 0.02],
        ['galpão industrial oeste catarinense', 0.04, 3.2, 0.09], ['tabela de vão estrutura metálica', 0.03, 7.4, 0.04],
    ];

    private const GSC_PAGES = [
        ['/', 0.36], ['/galpoes-industriais', 0.18], ['/mezaninos', 0.12], ['/blog/estrutura-metalica-ou-concreto', 0.10],
        ['/orcamento', 0.08], ['/coberturas', 0.06], ['/catalogo-tecnico', 0.05], ['/obras-entregues', 0.05],
    ];

    private const SITE = 'https://auroraestruturas.com.br';

    public function can(string $scope): bool
    {
        return true;
    }

    public function ga4Daily(string $propertyId, int $days = 28): array
    {
        return ['ok' => true, 'days' => collect(range($days, 1))->map(function ($ago) {
            $date = today()->subDays($ago);

            return ['date' => $date->toDateString()] + $this->day($date);
        })->all()];
    }

    public function ga4Summary(string $propertyId, int $days = 28): array
    {
        $daily = collect($this->ga4Daily($propertyId, $days)['days']);
        $sessions = $daily->sum('sessions');
        $events = $daily->sum('key_events');

        return [
            'ok'       => true,
            'totals'   => ['sessions' => $sessions, 'users' => (int) round($sessions * 0.78), 'key_events' => $events, 'engagement' => 61],
            'channels' => collect(self::CHANNELS)->map(fn ($c) => [
                'name' => $c[0], 'sessions' => (int) round($sessions * $c[1]), 'key_events' => (int) round($sessions * $c[1] * $c[2]),
            ])->all(),
            'pages'    => collect(self::PAGES)->map(fn ($p) => [
                'page' => $p[0], 'sessions' => (int) round($sessions * $p[1]), 'key_events' => (int) round($sessions * $p[1] * $p[2]),
            ])->all(),
        ];
    }

    public function searchConsoleSummary(string $site, int $days = 28): array
    {
        // Busca orgânica ≈ 34% das visitas; o Google fecha os números ~2 dias depois.
        $clicks = (int) round(collect(range($days + 2, 3))->sum(fn ($ago) => $this->day(today()->subDays($ago))['sessions']) * 0.34);
        $impressions = (int) round($clicks / 0.041);

        return [
            'ok'      => true,
            'totals'  => ['clicks' => $clicks, 'impressions' => $impressions, 'ctr' => round($clicks / max(1, $impressions) * 100, 1), 'position' => 9.4],
            'queries' => collect(self::QUERIES)->map(fn ($q) => [
                'key' => $q[0], 'clicks' => (int) round($clicks * $q[1]), 'impressions' => (int) round($clicks * $q[1] / $q[3]), 'position' => $q[2],
            ])->all(),
            'pages'   => collect(self::GSC_PAGES)->map(fn ($p) => [
                'key' => self::SITE . $p[0], 'clicks' => (int) round($clicks * $p[1]), 'impressions' => (int) round($clicks * $p[1] / 0.04), 'position' => 8.0,
            ])->all(),
        ];
    }

    /** @return array{sessions: int, key_events: int} */
    private function day(Carbon $date): array
    {
        mt_srand((int) $date->format('Ymd'));
        $weekday = [0.55, 1.0, 1.06, 1.04, 1.0, 0.92, 0.62][$date->dayOfWeek];
        // Crescimento leve ao longo dos meses (o site e as campanhas amadurecendo).
        $trend = 1 + min(0.35, max(0, $date->diffInDays(Carbon::parse('2026-06-01'), true) / 600));
        $sessions = (int) round(205 * $weekday * $trend * (0.85 + mt_rand(0, 30) / 100));
        $events = (int) round($sessions * (0.024 + mt_rand(0, 12) / 1000));
        mt_srand();

        return ['sessions' => $sessions, 'key_events' => $events];
    }
}

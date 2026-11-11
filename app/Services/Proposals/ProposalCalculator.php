<?php

namespace App\Services\Proposals;

/**
 * Cálculo de uma proposta — fonte única (a tela recalcula chamando o servidor; o que é salvo
 * sai daqui). Funções puras sobre arrays, sem banco, testadas em tests/Unit/ProposalCalculatorTest
 * com os 5 casos da "Calculadora de Orçamento do PlaNo C · Especificação v1.0".
 *
 * Entrada:
 *  package: null | ['id','name','monthly','one_time','type' => closed|open,'rules' => [...]]
 *  lines:   [['id','name','kind' => monthly|one_time,'unit_price','quantity','rules' => [...]], ...]
 *  ad_budget (verba média mensal), discount (%), installments,
 *  group: ['on' => bool,'name','mode' => sum|fixed,'monthly','one_time'] — itens avulsos vendidos como pacote
 */
class ProposalCalculator
{
    public function __construct(
        private float $freeDiscount = 5,
        private float $maxDiscount = 10,
        private float $upgradeThreshold = 1.20,
        private int $months = 12,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (float) config('proposals.free_discount_percent', 5),
            (float) config('proposals.max_discount_percent', 10),
            (float) config('proposals.upgrade_threshold', 1.20),
            (int) config('proposals.contract_months', 12),
        );
    }

    public function calculate(array $in): array
    {
        $P = $in['package'] ?? null;
        $lines = $in['lines'] ?? [];
        $budget = (float) ($in['ad_budget'] ?? 0);
        $discount = (float) ($in['discount'] ?? 0);
        $installments = max(1, (int) ($in['installments'] ?? 1));
        $group = $in['group'] ?? ['on' => false];

        $pkgMonthly = $P ? (float) $P['monthly'] : 0.0;
        $pkgOneTime = $P ? (float) $P['one_time'] : 0.0;
        $closed = $P && ($P['type'] ?? null) === 'closed';

        $looseMonthly = 0.0;
        $looseOneTime = 0.0;
        $lineTotals = [];
        foreach ($lines as $l) {
            $sub = $this->r2((float) $l['unit_price'] * (int) $l['quantity']);
            $lineTotals[] = $sub;
            $l['kind'] === 'monthly' ? $looseMonthly += $sub : $looseOneTime += $sub;
        }
        $looseMonthly = $this->r2($looseMonthly);
        $looseOneTime = $this->r2($looseOneTime);

        // Itens avulsos vendidos como um pacote personalizado
        $grouped = !empty($group['on']) && count($lines) >= 2;
        $groupMonthly = $looseMonthly;
        $groupOneTime = $looseOneTime;
        $groupBelowPercent = 0.0;
        if ($grouped && ($group['mode'] ?? 'sum') === 'fixed') {
            $groupMonthly = $this->r2((float) ($group['monthly'] ?? 0));
            $groupOneTime = $this->r2((float) ($group['one_time'] ?? 0));
            $sum = $looseMonthly * $this->months + $looseOneTime;
            $fixed = $groupMonthly * $this->months + $groupOneTime;
            $groupBelowPercent = $sum > 0 ? $this->r2((1 - $fixed / $sum) * 100) : 0.0;
        }

        // Verba acima do teto do pacote fechado (não entra no desconto)
        $cap = $closed ? ($P['rules']['ad_budget_cap'] ?? null) : null;
        $excess = $cap !== null ? max(0, $budget - (float) $cap) : 0.0;
        $excessValue = $this->r2($excess * (float) ($P['rules']['excess_rate'] ?? 0.10));

        $discountBase = $this->r2($pkgMonthly + $groupMonthly);
        $discountValue = $this->r2($discountBase * $discount / 100);
        $monthly = $this->r2($discountBase - $discountValue + $excessValue);
        $oneTime = $this->r2($pkgOneTime + $groupOneTime);
        $installment = $this->r2($oneTime / $installments);
        $lastInstallment = $this->r2($oneTime - $installment * ($installments - 1));

        $effectiveDiscount = max($discount, $groupBelowPercent);
        $approval = $effectiveDiscount > $this->maxDiscount ? 'blocked'
            : ($effectiveDiscount > $this->freeDiscount ? 'needs_release' : 'free');

        return [
            'package_monthly'     => $pkgMonthly,
            'package_one_time'    => $pkgOneTime,
            'line_totals'         => $lineTotals,
            'loose_monthly'       => $looseMonthly,
            'loose_one_time'      => $looseOneTime,
            'grouped'             => $grouped,
            'group_monthly'       => $groupMonthly,
            'group_one_time'      => $groupOneTime,
            'group_below_percent' => $groupBelowPercent,
            'excess'              => $this->r2($excess),
            'excess_value'        => $excessValue,
            'discount_base'       => $discountBase,
            'discount_value'      => $discountValue,
            'monthly'             => $monthly,
            'one_time'            => $oneTime,
            'installments'        => $installments,
            'installment'         => $installment,
            'last_installment'    => $lastInstallment,
            'first_months'        => $this->r2($monthly + $installment),
            'total'               => $this->r2($monthly * $this->months + $oneTime),
            'effective_discount'  => $this->r2($effectiveDiscount),
            'approval'            => $approval,
            'deliverables'        => $closed ? $this->deliverables($P, $lines) : null,
            'captured_videos'     => array_sum(array_map(fn ($l) => (int) ($l['rules']['yields_videos'] ?? 0) * (int) $l['quantity'], $lines)),
        ];
    }

    /** Volumes do mês num pacote fechado: o do nível + o que os adicionais somam. */
    public function deliverables(array $P, array $lines): array
    {
        $r = $P['rules'] ?? [];
        $sum = fn (string $k) => array_sum(array_map(fn ($l) => (int) ($l['rules']['effects'][$k] ?? 0) * (int) $l['quantity'], $lines));

        return [
            'ads'        => (int) ($r['ads_per_month'] ?? 0) + $sum('ads'),
            'videos'     => (int) ($r['videos_per_month'] ?? 0) + $sum('videos'),
            'campaigns'  => (int) ($r['concurrent_campaigns'] ?? 0) + $sum('campaigns'),
            'attendants' => ($r['notified_attendants'] ?? null) === null ? null : (int) $r['notified_attendants'] + $sum('attendants'),
        ];
    }

    /**
     * Sugestão de subir pro nível de cima: recalcula com o próximo pacote absorvendo o que ele
     * já inclui (vídeos, anúncios, vagas de campanha, atendentes, verba, itens que não existem
     * nele). Devolve null quando não compensa (mais caro que threshold × mensalidade atual).
     */
    public function upgrade(array $in, array $current, array $next): ?array
    {
        $d = $current['deliverables'];
        $N = $next['rules'] ?? [];
        $absorbed = [];
        $lines = [];

        // Vídeos extras primeiro: cada um também soma anúncio
        $videosNeeded = max(0, $d['videos'] - (int) ($N['videos_per_month'] ?? 0));
        $adsFromVideoLines = 0;

        foreach ($in['lines'] as $l) {
            $fx = $l['rules']['effects'] ?? [];
            $q = (int) $l['quantity'];
            if (in_array($next['id'], $l['rules']['not_in_packages'] ?? [], true)) {
                $absorbed[] = $l['name'];
                continue;
            }
            if (!empty($fx['videos'])) {
                $nq = (int) ceil($videosNeeded / $fx['videos']);
                $videosNeeded = max(0, $videosNeeded - $nq * $fx['videos']);
                $adsFromVideoLines += $nq * (int) ($fx['ads'] ?? 0);
                $q = $this->absorb($l, $q, $nq, $absorbed);
            }
            $lines[] = ['q' => $q] + $l;
        }

        $adsNeeded = max(0, $d['ads'] - (int) ($N['ads_per_month'] ?? 0) - $adsFromVideoLines);
        $campaignsNeeded = max(0, $d['campaigns'] - (int) ($N['concurrent_campaigns'] ?? 0));
        $attendantsNeeded = ($N['notified_attendants'] ?? null) === null || $d['attendants'] === null
            ? 0 : max(0, $d['attendants'] - (int) $N['notified_attendants']);

        $final = [];
        foreach ($lines as $l) {
            $fx = $l['rules']['effects'] ?? [];
            $q = $l['q'];
            if (empty($fx['videos'])) {
                if (!empty($fx['ads'])) {
                    $nq = (int) ceil($adsNeeded / $fx['ads']);
                    $adsNeeded = max(0, $adsNeeded - $nq * $fx['ads']);
                    $q = $this->absorb($l, $q, $nq, $absorbed);
                } elseif (!empty($fx['campaigns'])) {
                    $q = $this->absorb($l, $q, (int) ceil($campaignsNeeded / $fx['campaigns']), $absorbed);
                } elseif (!empty($fx['attendants'])) {
                    $q = $this->absorb($l, $q, (int) ceil($attendantsNeeded / $fx['attendants']), $absorbed);
                }
            }
            unset($l['q']);
            if ($q > 0) {
                $final[] = ['quantity' => $q] + $l;
            }
        }

        if ($current['excess'] > 0 && (float) ($in['ad_budget'] ?? 0) <= (float) ($N['ad_budget_cap'] ?? 0)) {
            $absorbed[] = 'a verba acima do teto';
        }

        $nextIn = ['package' => $next, 'lines' => $final] + $in;
        $nextCalc = $this->calculate($nextIn);

        if ($nextCalc['monthly'] > $current['monthly'] * $this->upgradeThreshold) {
            return null;
        }

        return [
            'package_id'      => $next['id'],
            'package_name'    => $next['name'],
            'monthly'         => $nextCalc['monthly'],
            'one_time'        => $nextCalc['one_time'],
            'monthly_diff'    => $this->r2($nextCalc['monthly'] - $current['monthly']),
            'one_time_diff'   => $this->r2($nextCalc['one_time'] - $current['one_time']),
            'absorbed'        => array_values(array_unique($absorbed)),
            'lines'           => array_map(fn ($l) => ['id' => $l['id'], 'quantity' => $l['quantity']], $final),
        ];
    }

    private function absorb(array $line, int $q, int $newQ, array &$absorbed): int
    {
        if ($newQ < $q) {
            $absorbed[] = $line['name'];
            return $newQ;
        }
        return $q;
    }

    private function r2(float $v): float
    {
        return round($v + 0.0, 2);
    }
}

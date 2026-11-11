<?php

namespace Tests\Unit;

use App\Services\Proposals\ProposalCalculator;
use PHPUnit\Framework\TestCase;

/** Casos de teste da "Calculadora de Orçamento do PlaNo C · Especificação v1.0" (seção 11). */
class ProposalCalculatorTest extends TestCase
{
    private ProposalCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new ProposalCalculator(5, 10, 1.20, 12);
    }

    private function tier(string $id, float $monthly, float $impl, int $ads, int $videos, int $camp, float $cap, ?int $att, ?string $next): array
    {
        return ['id' => $id, 'name' => "PlaNo C · $id", 'monthly' => $monthly, 'one_time' => $impl, 'type' => 'closed', 'rules' => [
            'ads_per_month' => $ads, 'videos_per_month' => $videos, 'concurrent_campaigns' => $camp,
            'ad_budget_cap' => $cap, 'notified_attendants' => $att, 'excess_rate' => 0.10, 'upgrade_to' => $next,
        ]];
    }

    private function base(): array { return $this->tier('BASE', 2500, 1500, 12, 1, 2, 3000, 1, 'AVANCADO'); }
    private function avancado(): array { return $this->tier('AVANCADO', 4200, 2500, 20, 3, 3, 8000, 5, 'COMPLETO'); }
    private function completo(): array { return $this->tier('COMPLETO', 6500, 3500, 30, 5, 5, 15000, null, null); }

    private function line(string $id, string $kind, float $price, int $q = 1, array $rules = []): array
    {
        return ['id' => $id, 'name' => $id, 'kind' => $kind, 'unit_price' => $price, 'quantity' => $q, 'rules' => $rules];
    }

    private function anuncios5(int $q = 1): array { return $this->line('REC_ANUNCIOS_5', 'monthly', 600, $q, ['requires_closed_package' => true, 'effects' => ['ads' => 5]]); }
    private function video(int $q = 1): array { return $this->line('REC_VIDEO', 'monthly', 350, $q, ['requires_closed_package' => true, 'effects' => ['ads' => 1, 'videos' => 1]]); }

    public function test_caso_1_base_puro(): void
    {
        $r = $this->calc->calculate(['package' => $this->base(), 'lines' => [], 'ad_budget' => 2000, 'discount' => 0, 'installments' => 3]);

        $this->assertSame(2500.0, $r['monthly']);
        $this->assertSame(1500.0, $r['one_time']);
        $this->assertSame(500.0, $r['installment']);
        $this->assertSame(3000.0, $r['first_months']);
        $this->assertSame(31500.0, $r['total']);
        $this->assertSame(12, $r['deliverables']['ads']);
        $this->assertSame(1, $r['deliverables']['videos']);
        $this->assertNull($this->calc->upgrade(['package' => $this->base(), 'lines' => [], 'ad_budget' => 2000, 'discount' => 0, 'installments' => 3], $r, $this->avancado()));
    }

    public function test_caso_2_base_com_adicionais_sugere_upgrade(): void
    {
        $in = ['package' => $this->base(), 'lines' => [$this->anuncios5(), $this->video()], 'ad_budget' => 5000, 'discount' => 0, 'installments' => 1];
        $r = $this->calc->calculate($in);

        $this->assertSame(200.0, $r['excess_value']);
        $this->assertSame(3650.0, $r['monthly']);
        $this->assertSame(18, $r['deliverables']['ads']);
        $this->assertSame(2, $r['deliverables']['videos']);

        $up = $this->calc->upgrade($in, $r, $this->avancado());
        $this->assertNotNull($up);
        $this->assertSame(4200.0, $up['monthly']);
        $this->assertSame(550.0, $up['monthly_diff']);
        $this->assertSame(1000.0, $up['one_time_diff']);
        $this->assertSame([], $up['lines']);
    }

    public function test_caso_3_avancado_com_captacao_lp_e_desconto(): void
    {
        $in = ['package' => $this->avancado(), 'lines' => [
            $this->line('REC_CAPTACAO', 'monthly', 1800, 1, ['yields_videos' => 4]),
            $this->line('UNI_LP', 'one_time', 1297),
        ], 'ad_budget' => 6000, 'discount' => 5, 'installments' => 6];
        $r = $this->calc->calculate($in);

        $this->assertSame(6000.0, $r['discount_base']);
        $this->assertSame(300.0, $r['discount_value']);
        $this->assertSame(5700.0, $r['monthly']);
        $this->assertSame(3797.0, $r['one_time']);
        $this->assertSame(632.83, $r['installment']);
        $this->assertSame(632.85, $r['last_installment']);
        $this->assertSame(6332.83, $r['first_months']);
        $this->assertSame(72197.0, $r['total']);
        $this->assertSame('free', $r['approval']);
        $this->assertSame(4, $r['captured_videos']);
        $this->assertNull($this->calc->upgrade($in, $r, $this->completo()));
    }

    public function test_caso_4_completo_verba_alta_campanha_extra(): void
    {
        $r = $this->calc->calculate(['package' => $this->completo(), 'lines' => [
            $this->line('REC_CAMPANHA', 'monthly', 450, 1, ['requires_closed_package' => true, 'effects' => ['campaigns' => 1]]),
        ], 'ad_budget' => 20000, 'discount' => 0, 'installments' => 3]);

        $this->assertSame(500.0, $r['excess_value']);
        $this->assertSame(7450.0, $r['monthly']);
        $this->assertSame(1166.67, $r['installment']);
        $this->assertSame(92900.0, $r['total']);
        $this->assertSame(30, $r['deliverables']['ads']);
        $this->assertSame(5, $r['deliverables']['videos']);
        $this->assertSame(6, $r['deliverables']['campaigns']);
        $this->assertNull($r['deliverables']['attendants']);
    }

    public function test_caso_5_faixas_de_desconto(): void
    {
        $in = fn ($d) => ['package' => $this->base(), 'lines' => [], 'ad_budget' => 0, 'discount' => $d, 'installments' => 1];

        $this->assertSame('free', $this->calc->calculate($in(5))['approval']);
        $this->assertSame('needs_release', $this->calc->calculate($in(8))['approval']);
        $this->assertSame('blocked', $this->calc->calculate($in(12))['approval']);
    }

    public function test_desconto_nao_incide_na_verba_excedente(): void
    {
        $r = $this->calc->calculate(['package' => $this->base(), 'lines' => [], 'ad_budget' => 5000, 'discount' => 10, 'installments' => 1]);

        $this->assertSame(250.0, $r['discount_value']);
        $this->assertSame(2450.0, $r['monthly']); // 2500 - 250 + 200
    }

    public function test_proposta_avulsa_com_pacote_personalizado_preco_fechado(): void
    {
        $lines = [
            $this->line('UNI_SITE', 'one_time', 3597),
            $this->line('UNI_FOTO', 'one_time', 900),
            $this->line('UNI_CAPTACAO', 'one_time', 1800),
            $this->line('REC_HOSPEDAGEM', 'monthly', 69.90),
        ];
        $r = $this->calc->calculate(['package' => null, 'lines' => $lines, 'discount' => 0, 'installments' => 3,
            'group' => ['on' => true, 'mode' => 'fixed', 'monthly' => 69.90, 'one_time' => 5990]]);

        $this->assertSame(69.9, $r['monthly']);
        $this->assertSame(5990.0, $r['one_time']);
        $this->assertSame(6828.8, $r['total']);
        $this->assertSame(4.3, $r['group_below_percent']);
        $this->assertSame('free', $r['approval']);
        $this->assertNull($r['deliverables']);

        // Preço fechado 8% abaixo da soma conta como desconto → precisa de liberação
        $r2 = $this->calc->calculate(['package' => null, 'lines' => $lines, 'discount' => 0, 'installments' => 1,
            'group' => ['on' => true, 'mode' => 'fixed', 'monthly' => 69.90, 'one_time' => 5700]]);
        $this->assertSame('needs_release', $r2['approval']);
    }

    public function test_upgrade_pro_completo_absorve_reuniao_e_atendentes(): void
    {
        $in = ['package' => $this->avancado(), 'lines' => [
            $this->line('REC_REUNIAO_EST', 'monthly', 400, 2, ['requires_closed_package' => true, 'not_in_packages' => ['COMPLETO']]),
            $this->line('REC_ATENDENTE', 'monthly', 40, 3, ['requires_closed_package' => true, 'not_in_packages' => ['COMPLETO'], 'effects' => ['attendants' => 1]]),
            $this->anuncios5(2),
        ], 'ad_budget' => 9000, 'discount' => 0, 'installments' => 1];
        $r = $this->calc->calculate($in);
        $this->assertSame(4200 + 800 + 120 + 1200 + 100.0, $r['monthly']); // 6420

        $up = $this->calc->upgrade($in, $r, $this->completo());
        $this->assertNotNull($up);
        $this->assertSame(6500.0, $up['monthly']);
        $this->assertSame([], $up['lines']);
        $this->assertContains('a verba acima do teto', $up['absorbed']);
    }
}

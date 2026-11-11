<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Catálogo de Produtos e Serviços (Central de Comércio → Propostas).
 *
 * Um item é: serviço mensal (monthly), produto/projeto único (one_time) ou pacote (package).
 * Pacote "closed" = nível do PlaNo C (volumes, teto de verba, só soma na proposta);
 * pacote "open" = montado pela Nonna a partir de outros itens (catalog_package_items).
 * Todo item guarda os dois preços (mensal e único) — serviço usa só o mensal, produto só
 * o único, pacote os dois (mensalidade + implantação).
 *
 * Nasce com o catálogo da "Calculadora de Orçamento do PlaNo C · Especificação v1.0"
 * (11/11/2026), só na organização Nonna, e só se ela ainda não tiver itens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60)->nullable();
            $table->string('line', 60);
            $table->string('kind', 20);                 // monthly | one_time | package
            $table->string('package_type', 20)->nullable(); // closed | open (só pacote)
            $table->string('name', 160);
            $table->text('description')->nullable();    // regra curta mostrada na proposta
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->decimal('one_time_price', 12, 2)->nullable();
            $table->boolean('price_is_starting')->default(false); // "a partir de": vendedor ajusta
            $table->string('value_status', 20)->default('suggested'); // decided | suggested
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'kind', 'is_active']);
        });

        Schema::create('catalog_package_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('package_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('catalog_items')->restrictOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->unique(['package_id', 'item_id']);
        });

        $org = DB::table('organizations')->where('slug', 'nonna')->first();
        if ($org && !DB::table('catalog_items')->where('organization_id', $org->id)->exists()) {
            $this->seed($org->id);
        }
    }

    private function seed(string $orgId): void
    {
        $now = now();
        $ids = [];
        foreach (['BASE', 'AVANCADO', 'COMPLETO'] as $code) {
            $ids[$code] = (string) Str::uuid();
        }

        $tiers = [
            'BASE' => [
                'name' => 'PlaNo C · Base', 'monthly' => 2500, 'impl' => 1500, 'next' => 'AVANCADO', 'recommended' => false,
                'question' => 'De onde vêm meus contatos e quantos viram venda?',
                'r' => ['ads_per_month' => 12, 'videos_per_month' => 1, 'concurrent_campaigns' => 2, 'ad_budget_cap' => 3000, 'notified_attendants' => 1],
                'includes' => ['12 anúncios por mês, até 1 vídeo', 'Gestão de Meta e Google com verba até R$ 3 mil', '1 landing page com hospedagem', 'Central de Leads e aviso de contato novo no WhatsApp (1 atendente)', 'Resumo mensal do atendimento', 'Macroplanejamento de 90 dias'],
            ],
            'AVANCADO' => [
                'name' => 'PlaNo C · Avançado', 'monthly' => 4200, 'impl' => 2500, 'next' => 'COMPLETO', 'recommended' => true,
                'question' => 'O que dá resultado e onde invisto mais?',
                'r' => ['ads_per_month' => 20, 'videos_per_month' => 3, 'concurrent_campaigns' => 3, 'ad_budget_cap' => 8000, 'notified_attendants' => 5],
                'includes' => ['20 anúncios por mês, até 3 vídeos', 'Gestão de Meta e Google com verba até R$ 8 mil', 'Até 3 landing pages ou 1 site', 'Recuperação de vendas', 'Diagnóstico completo do atendimento, mensal', 'Gestores todo mês'],
            ],
            'COMPLETO' => [
                'name' => 'PlaNo C · Completo', 'monthly' => 6500, 'impl' => 3500, 'next' => null, 'recommended' => false,
                'question' => 'Onde minha equipe perde venda e como corrijo?',
                'r' => ['ads_per_month' => 30, 'videos_per_month' => 5, 'concurrent_campaigns' => 5, 'ad_budget_cap' => 15000, 'notified_attendants' => null],
                'includes' => ['30 anúncios por mês, até 5 vídeos', 'Gestão de Meta e Google com verba até R$ 15 mil', 'Site e 1 landing page por campanha ativa', 'Diagnóstico quinzenal do atendimento e nota por atendente', 'Estrategista e gestores todo mês', 'Aviso de contato novo sem limite de atendentes'],
            ],
        ];

        $rows = [];
        $order = 0;
        foreach ($tiers as $code => $t) {
            $rows[] = [
                'id' => $ids[$code], 'code' => 'PLANOC_' . $code, 'line' => 'PlaNo C', 'kind' => 'package', 'package_type' => 'closed',
                'name' => $t['name'], 'description' => $t['question'],
                'monthly_price' => $t['monthly'], 'one_time_price' => $t['impl'], 'price_is_starting' => false, 'value_status' => 'decided',
                'rules' => json_encode($t['r'] + [
                    'excess_rate' => 0.10,
                    'upgrade_to' => $t['next'] ? $ids[$t['next']] : null,
                    'recommended' => $t['recommended'],
                    'includes' => $t['includes'],
                ], JSON_UNESCAPED_UNICODE),
                'sort_order' => $order++,
            ];
        }

        $planoC = ['requires_closed_package' => true];
        $notCompleto = ['not_in_packages' => [$ids['COMPLETO']]];
        $items = [
            // code, line, kind, name, description, price, starting, status, rules
            ['REC_ANUNCIOS_5', 'PlaNo C', 'monthly', 'Pacote de +5 anúncios', 'Card, carrossel ou motion · R$ 120 por anúncio', 600, false, 'suggested', $planoC + ['effects' => ['ads' => 5]]],
            ['REC_VIDEO', 'PlaNo C', 'monthly', 'Vídeo extra', 'Edição de material do cliente ou de captação · conta como anúncio', 350, false, 'suggested', $planoC + ['effects' => ['ads' => 1, 'videos' => 1]]],
            ['REC_CAMPANHA', 'PlaNo C', 'monthly', 'Campanha ou projeto simultâneo extra', 'Uma vaga a mais ao mesmo tempo', 450, false, 'suggested', $planoC + ['effects' => ['campaigns' => 1]]],
            ['REC_ATENDENTE', 'PlaNo C', 'monthly', 'Atendente extra no aviso de contato novo', 'Por atendente', 40, false, 'suggested', $planoC + $notCompleto + ['not_in_reason' => 'O Completo já não tem limite de atendentes', 'effects' => ['attendants' => 1]]],
            ['REC_REUNIAO_EST', 'PlaNo C', 'monthly', 'Reunião extra com o estrategista', 'Por reunião no mês', 400, false, 'suggested', $planoC + $notCompleto + ['not_in_reason' => 'No Completo o estrategista já participa todo mês']],
            ['REC_CAPTACAO', 'Produtora', 'monthly', 'Captação mensal', 'Uma diária por mês · até 8 reels ou 4 vídeos', 1800, true, 'decided', ['yields_videos' => 4]],
            ['REC_HOSPEDAGEM', 'Web', 'monthly', 'Hospedagem de site ou domínio extra', 'Por site além do que o pacote inclui', 69.90, false, 'decided', null],
            ['UNI_LP', 'Web', 'one_time', 'Landing page', 'Já nasce rastreada', 1297, false, 'decided', null],
            ['UNI_SITE', 'Web', 'one_time', 'Site institucional', null, 3597, false, 'decided', null],
            ['UNI_ECOMMERCE', 'Web', 'one_time', 'E-commerce', 'Projeto à parte', 4997, false, 'decided', null],
            ['UNI_CAPTACAO', 'Produtora', 'one_time', 'Captação avulsa', 'Um dia · até 8 reels ou 4 vídeos', 1800, true, 'decided', ['yields_videos' => 4]],
            ['UNI_VIDEO_INST', 'Produtora', 'one_time', 'Vídeo institucional (até 3 min)', null, 2800, true, 'decided', null],
            ['UNI_MOTION', 'Produtora', 'one_time', 'Motion especial', 'Vinheta ou explicativo', 900, true, 'decided', null],
            ['UNI_FOTO', 'Produtora', 'one_time', 'Fotografia (produto, ambiente ou equipe)', null, 900, true, 'decided', null],
        ];
        foreach ($items as [$code, $line, $kind, $name, $desc, $price, $starting, $status, $rules]) {
            $rows[] = [
                'id' => (string) Str::uuid(), 'code' => $code, 'line' => $line, 'kind' => $kind, 'package_type' => null,
                'name' => $name, 'description' => $desc,
                'monthly_price' => $kind === 'monthly' ? $price : null,
                'one_time_price' => $kind === 'one_time' ? $price : null,
                'price_is_starting' => $starting, 'value_status' => $status,
                'rules' => $rules ? json_encode($rules, JSON_UNESCAPED_UNICODE) : null,
                'sort_order' => $order++,
            ];
        }

        DB::table('catalog_items')->insert(array_map(fn ($r) => $r + [
            'organization_id' => $orgId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], $rows));
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_package_items');
        Schema::dropIfExists('catalog_items');
    }
};

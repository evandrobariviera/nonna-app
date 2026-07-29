<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientAdAccount extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'platform',
        'platform_custom',
        'account_id',
        'account_name',
        'sheet_tab_name',
        'status',
        'notes',
        'created_by',
        'payment_method',
        'balance',
        'balance_source',
        'balance_synced_at',
        'budget_automation_enabled',
        'last_billing_sent_at',
        'budget_status',
        'responsible_user_id',
        'target_cost_per_result',
        'target_roas',
    ];

    protected $casts = [
        'balance'                    => 'decimal:2',
        'balance_synced_at'          => 'datetime',
        'budget_automation_enabled'  => 'boolean',
        'last_billing_sent_at'       => 'datetime',
        'target_cost_per_result'     => 'decimal:2',
        'target_roas'                => 'decimal:2',
    ];

    public static array $platforms = [
        'meta_ads'            => 'Meta Ads (Facebook/Instagram)',
        'meta_bm'             => 'Meta Business Manager',
        'google_ads'          => 'Google Ads',
        'google_analytics'    => 'Google Analytics 4',
        'google_tag_manager'  => 'Google Tag Manager',
        'tiktok_ads'          => 'TikTok Ads',
        'linkedin_ads'        => 'LinkedIn Ads',
        'pinterest_ads'       => 'Pinterest Ads',
        'outros'              => 'Outros',
    ];

    public static array $statuses = [
        'ativo'    => ['label' => 'Ativo',    'color' => 'green'],
        'pausado'  => ['label' => 'Pausado',  'color' => 'orange'],
        'suspenso' => ['label' => 'Suspenso', 'color' => 'red'],
    ];

    // Mesmos rótulos de Client::$paymentMethods — atributo da conta, não do cliente
    // (um cliente pode ter Facebook em boleto e Google em cartão).
    public static array $paymentMethods = [
        'pix'    => 'PIX',
        'cartao' => 'Cartão',
        'boleto' => 'Boleto',
    ];

    // Plataformas de mídia paga de verdade — as demais (BM, GA4, GTM, Outros)
    // não têm saldo/custo diário pra controlar.
    private const BILLING_PLATFORMS = ['meta_ads', 'google_ads', 'tiktok_ads', 'linkedin_ads', 'pinterest_ads'];

    // Mesmos 4 status da lista "Orçamentos" no ClickUp — controle manual do
    // gestor sobre a situação daquela conta no momento, independente do saldo
    // calculado (uma conta pode estar com saldo ok mas ainda em "Standby"
    // porque o cliente não autorizou rodar, por exemplo).
    public static array $budgetStatuses = [
        'adicao_necessaria' => ['label' => 'Adição Necessária', 'color' => 'muted'],
        'aguardando_pagto'  => ['label' => 'Aguardando Pagto',  'color' => 'orange'],
        'creditos_ativos'   => ['label' => 'Créditos Ativos',   'color' => 'green'],
        'standby'           => ['label' => 'Standby',           'color' => 'orange'],
    ];

    public function platformLabel(): string
    {
        if ($this->platform === 'outros' && $this->platform_custom) {
            return $this->platform_custom;
        }
        return self::$platforms[$this->platform] ?? $this->platform;
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function budgetStatusLabel(): string
    {
        return self::$budgetStatuses[$this->budget_status]['label'] ?? $this->budget_status;
    }

    public function budgetStatusColor(): string
    {
        return self::$budgetStatuses[$this->budget_status]['color'] ?? 'muted';
    }

    // Gestão automática de budget_status — chamada 1x/dia em SyncAdPlatforms
    // (depois do saldo do dia atualizado) e sempre que um boleto/PIX é
    // enviado (ClientAdBillingDocumentController::store()).
    //
    // Regras confirmadas com o usuário:
    // - standby trava tudo — só sai manualmente, automação nunca mexe aqui
    // - saldo > 0 e dias restantes > 7 → creditos_ativos
    // - caso contrário → adicao_necessaria, EXCETO se já estiver
    //   aguardando_pagto (só sai desse quando o saldo se recupera pra
    //   creditos_ativos — nunca regride sozinho pra adicao_necessaria
    //   enquanto espera o cliente pagar o boleto já enviado)
    // - "Automação" (budget_automation_enabled) liga/desliga essa reavaliação
    //   diária por saldo; a transição pra aguardando_pagto ao enviar um
    //   boleto roda sempre (é ação direta do usuário, não do robô).
    public function applyAutomaticBudgetStatus(): void
    {
        if (!$this->budget_automation_enabled || !$this->hasBillingTracking() || $this->budget_status === 'standby') {
            return;
        }

        $days = $this->daysRemaining();
        $healthy = $this->balance !== null && (float) $this->balance > 0 && $days !== null && $days > 7;

        if ($healthy) {
            if ($this->budget_status !== 'creditos_ativos') {
                $this->update(['budget_status' => 'creditos_ativos']);
            }
            return;
        }

        if ($this->budget_status === 'aguardando_pagto') {
            return;
        }

        if ($this->budget_status !== 'adicao_necessaria') {
            $this->update(['budget_status' => 'adicao_necessaria']);
        }
    }

    // Chamado ao enviar um boleto/PIX novo — evento direto do usuário, roda
    // mesmo com "Automação" desligada; só respeita a trava de standby.
    public function markAwaitingPayment(): void
    {
        if ($this->budget_status !== 'standby' && $this->budget_status !== 'aguardando_pagto') {
            $this->update(['budget_status' => 'aguardando_pagto']);
        }
    }

    // Contas boleto/PIX fora do Meta não têm saldo exposto por API nenhuma
    // (é dado de faturamento, não de campanha) — mas dá pra manter um
    // "livro-caixa" sozinho: cada boleto/PIX enviado com valor credita o
    // saldo (ClientAdBillingDocumentController::store()), e o gasto real do
    // dia debita automaticamente (SyncAdPlatforms::checkLowBalances() →
    // debitLedgerBalances()). Meta fica de fora porque a API dele já devolve
    // o saldo verdadeiro (fetchAccountBalance) — usar as duas fontes juntas
    // duplicaria a contagem. Cartão (pós-pago automático) nunca tem saldo,
    // não importa a plataforma.
    public function usesLedgerBalance(): bool
    {
        return $this->hasBillingTracking()
            && $this->platform !== 'meta_ads'
            && in_array($this->payment_method, ['boleto', 'pix'], true);
    }

    public function balanceSourceLabel(): ?string
    {
        return match ($this->balance_source) {
            'api'    => 'API',
            'ledger' => 'Calculado',
            default  => null,
        };
    }

    public function billingDocuments(): HasMany
    {
        return $this->hasMany(ClientAdBillingDocument::class)->orderByDesc('created_at');
    }

    public function paymentMethodLabel(): string
    {
        return self::$paymentMethods[$this->payment_method] ?? ($this->payment_method ?? '—');
    }

    public function hasBillingTracking(): bool
    {
        return in_array($this->platform, self::BILLING_PLATFORMS, true);
    }

    public static function billingPlatforms(): array
    {
        return self::BILLING_PLATFORMS;
    }

    // Gasto médio diário dos últimos N dias, somando todas as campanhas da
    // conta (entity_level='campaign' evita contar adset/ad em duplicidade
    // com o rollup da campanha — mesmo filtro usado em AdCampaign::snapshots()).
    public function avgDailySpend(int $days = 7): float
    {
        $since = now()->subDays($days)->toDateString();

        $totalSpend = AdDailySnapshot::where('client_ad_account_id', $this->id)
            ->where('entity_level', 'campaign')
            ->where('snapshot_date', '>=', $since)
            ->sum('spend');

        return $days > 0 ? round((float) $totalSpend / $days, 2) : 0.0;
    }

    public function daysRemaining(): ?int
    {
        if ($this->balance === null) {
            return null;
        }

        $dailyCost = $this->avgDailySpend();
        if ($dailyCost <= 0) {
            return null;
        }

        return (int) floor((float) $this->balance / $dailyCost);
    }

    public function balanceStatusColor(): string
    {
        $days = $this->daysRemaining();

        if ($days === null) return 'muted';
        if ($days <= 3)     return 'red';
        if ($days <= 7)     return 'orange';

        return 'green';
    }

    public function balanceStatusLabel(): string
    {
        $days = $this->daysRemaining();

        return $days === null ? '—' : "{$days} dia" . ($days === 1 ? '' : 's');
    }
}

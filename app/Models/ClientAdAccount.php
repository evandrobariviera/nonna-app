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
    ];

    protected $casts = [
        'balance'                    => 'decimal:2',
        'balance_synced_at'          => 'datetime',
        'budget_automation_enabled'  => 'boolean',
        'last_billing_sent_at'       => 'datetime',
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

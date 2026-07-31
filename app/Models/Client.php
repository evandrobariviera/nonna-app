<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'clickup_task_id',
        'company_name',
        'is_internal',
        'logo_path',
        'logo_disk',
        'tax_id',
        'website',
        'segment',
        'status',
        'monthly_ad_budget',
        'contracted_services',
        // Empresa — contato
        'contact_email',
        'contact_phone',
        'address',
        'zip_code',
        // Responsável
        'responsible_name',
        'responsible_birthdate',
        'responsible_rg',
        'responsible_cpf',
        'responsible_address',
        'responsible_marital_status',
        // Cobrança
        'payment_method',
        'billing_day',
        'billing_email',
        'billing_whatsapp',
        'billing_notes',
        // Interno
        'notes',
        'registration_token',
        'registration_completed_at',
    ];

    protected $casts = [
        'contracted_services'       => 'array',
        'registration_completed_at' => 'datetime',
        'responsible_birthdate'     => 'date',
        'billing_day'               => 'integer',
        'is_internal'               => 'boolean',
    ];

    public static array $statuses = [
        'lead'     => ['label' => 'Lead',    'color' => 'muted'],
        'active'   => ['label' => 'Ativo',   'color' => 'green'],
        'inactive' => ['label' => 'Inativo', 'color' => 'red'],
    ];

    public static array $segments = [
        'Clínica / Saúde',
        'Educação',
        'E-commerce',
        'Imobiliário',
        'Restaurante / Food',
        'Varejo',
        'Serviços B2B',
        'Tecnologia',
        'Beleza & Estética',
        'Advocacia / Jurídico',
        'Outro',
    ];

    public static array $services = [
        'trafego'     => 'Tráfego Pago',
        'social'      => 'Social Media',
        'site'        => 'Site / Landing Page',
        'seo'         => 'SEO',
        'email'       => 'E-mail Marketing',
        'automacao'   => 'Automação',
        'consultoria' => 'Consultoria',
    ];

    public static array $paymentMethods = [
        'pix'    => 'PIX',
        'cartao' => 'Cartão',
        'boleto' => 'Boleto',
    ];

    public static array $billingDays = [10, 15, 20];

    public static array $maritalStatuses = [
        'solteiro'      => 'Solteiro(a)',
        'casado'        => 'Casado(a)',
        'divorciado'    => 'Divorciado(a)',
        'viuvo'         => 'Viúvo(a)',
        'uniao_estavel' => 'União Estável',
    ];

    public function generateRegistrationToken(): string
    {
        $token = Str::random(48);
        $this->update(['registration_token' => $token]);
        return $token;
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function logoUrl(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        if ($this->logo_disk === 'r2') {
            return Storage::disk('r2')->temporaryUrl($this->logo_path, now()->addHours(24));
        }

        return Storage::disk($this->logo_disk)->url($this->logo_path);
    }

    public function isRegistrationComplete(): bool
    {
        return $this->registration_completed_at !== null;
    }

    // ── Relacionamentos CRM Pipeline ──

    public function credentials(): HasMany
    {
        return $this->hasMany(ClientCredential::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ClientLink::class);
    }

    public function onboarding(): HasOne
    {
        return $this->hasOne(ClientOnboarding::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class)->orderByDesc('scheduled_at');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'client_contacts', 'client_id', 'contact_id')
            ->using(ClientContact::class)
            ->withPivot(['role', 'is_primary', 'portal_access_enabled'])
            ->withTimestamps();
    }

    public function adAccounts(): HasMany
    {
        return $this->hasMany(ClientAdAccount::class);
    }

    public function dossiers(): HasMany
    {
        return $this->hasMany(BrandDossier::class)->orderByDesc('version');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function macroplans(): HasMany
    {
        return $this->hasMany(MacroPlan::class)->orderByDesc('period_start');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->orderByDesc('start_date');
    }

    public function adBudgets(): HasMany
    {
        return $this->hasMany(ClientAdBudget::class)->orderByDesc('start_date')->orderByDesc('created_at');
    }

    public function currentAdBudget(): ?ClientAdBudget
    {
        return $this->adBudgets()->where('start_date', '<=', now())->first();
    }

    public function insights(): HasMany
    {
        return $this->hasMany(CampaignInsight::class)->orderByDesc('generated_at');
    }

    public function openInsightsCount(): int
    {
        return $this->insights()->whereIn('status', ['novo', 'lido'])->count();
    }

    public function currentMonthAdSpend(): float
    {
        $adAccountIds = $this->adAccounts()->pluck('id');

        if ($adAccountIds->isEmpty()) {
            return 0.0;
        }

        $total = DB::connection('pgsql')
            ->table('ad_daily_snapshots')
            ->whereIn('client_ad_account_id', $adAccountIds)
            ->where('entity_level', 'campaign')
            ->where('snapshot_date', '>=', now()->startOfMonth()->toDateString())
            ->sum('spend');

        return (float) $total;
    }

    public function primaryContact()
    {
        return $this->contacts()->wherePivot('is_primary', true)->first();
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(ClientIntegration::class);
    }

    public function serviceConversations(): HasMany
    {
        return $this->hasMany(ServiceConversation::class);
    }

    public function serviceDiagnostics(): HasMany
    {
        return $this->hasMany(ServiceDiagnostic::class)->orderByDesc('version');
    }

    public function currentServiceDiagnostic(): ?ServiceDiagnostic
    {
        return $this->serviceDiagnostics()->where('status', 'published')->first();
    }

    // Delete de verdade só é permitido quando não sobra NENHUMA associação —
    // devolve um mapa label => contagem só com o que estiver bloqueando
    // (array vazio = seguro apagar). Usado por ClientController::destroy().
    // Projeto e Tarefa não têm relação declarada aqui (client_id direto,
    // sem método hasMany dedicado) — checados por query direta.
    public function blockingAssociations(): array
    {
        $checks = [
            'contratos'                    => $this->contracts()->count(),
            'contas de anúncio'            => $this->adAccounts()->count(),
            'orçamentos de anúncios'       => $this->adBudgets()->count(),
            'planejamentos'                => $this->macroplans()->count(),
            'projetos'                     => Project::where('client_id', $this->id)->count(),
            'tarefas'                      => Task::where('client_id', $this->id)->count(),
            'dossiês de marca'             => $this->dossiers()->count(),
            'oportunidades'                => $this->opportunities()->count(),
            'contatos vinculados'          => $this->contacts()->count(),
            'integrações de atendimento'   => $this->integrations()->count(),
            'conversas de atendimento'     => $this->serviceConversations()->count(),
            'diagnósticos de atendimento assistido' => $this->serviceDiagnostics()->count(),
            'links salvos'                 => $this->links()->count(),
            'onboarding'                   => $this->onboarding ? 1 : 0,
        ];

        return array_filter($checks, fn ($count) => $count > 0);
    }
}

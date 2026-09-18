<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'organization_id',
        'clickup_task_id',
        'company_name',
        'nickname',
        'is_internal',
        'logo_path',
        'logo_disk',
        'tax_id',
        'website',
        'segment',
        'status',
        'monthly_ad_budget',
        'contracted_services',
        'creative_lead_id',
        'production_quota',
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
        'briefing',
        'registration_token',
        'registration_completed_at',
    ];

    protected $casts = [
        'contracted_services'       => 'array',
        'production_quota'          => 'array',
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

    // Tipos de tarefa que fazem sentido ter cota mensal — são os entregáveis que o
    // cliente contrata. Ficam de fora os tipos internos de operação (estratégia,
    // reuniões, administrativo): não são "material que dá pra pedir mais".
    // Ordem = uso real na carteira (criação e web concentram quase tudo).
    public static array $productionQuotaTypes = [
        'criacao', 'web', 'trafego', 'social', 'setup', 'seo', 'email',
    ];

    // Resultado de completeness() guardado por instância — ver o método.
    private ?array $completenessCache = null;

    // Respostas de existência trazidas em lote por primeCompleteness().
    private ?array $completenessHints = null;

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

    // Nome pra exibição operacional (listagens, dropdowns, cabeçalhos) — apelido
    // se tiver, senão a razão social. NUNCA usar em contrato, financeiro ou no
    // cadastro público: esses continuam mostrando company_name diretamente,
    // porque ali o nome legal é o que importa.
    public function displayName(): string
    {
        return $this->nickname ?: $this->company_name;
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

    public function credentialRequests(): HasMany
    {
        return $this->hasMany(ClientCredentialRequest::class);
    }

    // Pessoa da Nonna que organiza e distribui as tarefas deste cliente. Diferente
    // de responsible_name (representante legal do cliente) e de
    // client_ad_accounts.responsible_user_id (gestor daquela conta de anúncio).
    public function creativeLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creative_lead_id');
    }

    /**
     * Nota de preenchimento do cadastro (0-100), no espírito da nota de otimização do
     * Google Ads: mostra o quanto falta e o que exatamente falta.
     *
     * Regra que faz a nota ser levada a sério: item que não se aplica não conta. Cliente
     * que não contratou tráfego não perde ponto por não ter conta de anúncio — do
     * contrário ninguém atinge 100% e a nota vira ruído. Por isso o denominador é
     * variável, só com o que faz sentido pra aquele cliente.
     *
     * Cada chamada custa ~4 consultas de existência, então o resultado fica guardado na
     * instância — a tela do cliente chama mais de uma vez e a visão geral da produção
     * percorre a carteira inteira.
     *
     * @return array{score:int, done:int, total:int, groups:array}
     */
    public function completeness(): array
    {
        if ($this->completenessCache !== null) {
            return $this->completenessCache;
        }

        $temTrafego = in_array('trafego', $this->contracted_services ?? [], true);

        // [rótulo, preenchido?, aplica-se?, onde resolver (aba da ficha)]
        $itens = [
            'Identificação' => [
                ['Apelido',            filled($this->nickname),            true, 'geral'],
                ['Segmento',           filled($this->segment),             true, 'geral'],
                ['CNPJ/CPF',           filled($this->tax_id),              true, 'geral'],
                ['Site',               filled($this->website),             true, 'geral'],
            ],
            'Contato' => [
                ['E-mail',             filled($this->contact_email),       true, 'geral'],
                ['Telefone',           filled($this->contact_phone),       true, 'geral'],
                ['Contato vinculado',  $this->temVinculo('contato', fn () => $this->contacts()->exists()), true, 'contatos'],
            ],
            'Comercial' => [
                ['Serviços contratados', filled($this->contracted_services), true, 'geral'],
                ['Contrato ativo',       $this->temVinculo('contrato', fn () => $this->contracts()->where('status', 'ativo')->exists()), true, 'contratos'],
                ['Verba de tráfego',     filled($this->monthly_ad_budget),  $temTrafego, 'geral'],
            ],
            'Operação' => [
                ['Direção criativa',   filled($this->creative_lead_id),    true, 'geral'],
                ['Volume de produção', filled($this->production_quota),    true, 'geral'],
            ],
            'Estratégia' => [
                ['Briefing',           filled($this->briefing),            true, 'briefing'],
            ],
            'Mídia' => [
                ['Conta de anúncio',   $this->temVinculo('conta_anuncio', fn () => $this->adAccounts()->exists()),  $temTrafego, 'contas'],
                ['Fonte de lead',      $this->temVinculo('fonte_lead',    fn () => $this->leadSources()->exists()), $temTrafego, 'leads'],
            ],
        ];

        $done = $total = 0;
        $groups = [];

        foreach ($itens as $grupo => $linhas) {
            $lista = [];
            foreach ($linhas as [$label, $ok, $aplica, $aba]) {
                if (! $aplica) {
                    continue;
                }
                $total++;
                $done += $ok ? 1 : 0;
                $lista[] = ['label' => $label, 'ok' => (bool) $ok, 'tab' => $aba];
            }
            if ($lista) {
                $groups[$grupo] = $lista;
            }
        }

        $this->completenessHints = null; // já consumidos; libera memória em lista grande

        return $this->completenessCache = [
            'score'  => $total > 0 ? (int) round($done / $total * 100) : 100,
            'done'   => $done,
            'total'  => $total,
            'groups' => $groups,
        ];
    }

    /**
     * Existe vínculo desse tipo? Usa a resposta já trazida em lote por
     * primeCompleteness() quando houver; senão pergunta ao banco na hora.
     */
    private function temVinculo(string $chave, \Closure $consulta): bool
    {
        return $this->completenessHints[$chave] ?? $consulta();
    }

    /**
     * Responde de uma vez, pra uma carteira inteira, as quatro perguntas de existência
     * que a nota faz — em vez de quatro consultas por cliente. Sem isso, uma tela que
     * lista os 83 clientes dispara mais de 300 consultas só pra montar as notas.
     *
     * @param  \Illuminate\Support\Collection<int, self>  $clients
     */
    public static function primeCompleteness(\Illuminate\Support\Collection $clients): void
    {
        $ids = $clients->pluck('id')->all();

        if (! $ids) {
            return;
        }

        $comContato = \Illuminate\Support\Facades\DB::connection('pgsql')->table('client_contacts')
            ->whereIn('client_id', $ids)->distinct()->pluck('client_id')->flip();
        $comContrato = Contract::whereIn('client_id', $ids)
            ->where('status', 'ativo')->distinct()->pluck('client_id')->flip();
        $comConta = ClientAdAccount::whereIn('client_id', $ids)
            ->distinct()->pluck('client_id')->flip();
        $comFonte = ClientLeadSource::whereIn('client_id', $ids)
            ->distinct()->pluck('client_id')->flip();

        foreach ($clients as $client) {
            $client->completenessHints = [
                'contato'       => $comContato->has($client->id),
                'contrato'      => $comContrato->has($client->id),
                'conta_anuncio' => $comConta->has($client->id),
                'fonte_lead'    => $comFonte->has($client->id),
            ];
        }
    }

    /**
     * Cota mensal × o que já foi planejado no mês, por tipo de tarefa — responde
     * "quanto material ainda dá pra pedir". Conta pela data de aprovação (a data que
     * define a produção; ver Task::sprintDateCheck()), caindo pra data de criação
     * quando não houver, senão tarefa sem data sumiria da conta.
     *
     * Cancelada fica de fora; concluída conta, porque já consumiu a cota do mês.
     *
     * @return array<int, array{type:string, label:string, quota:int, used:int, left:int}>
     */
    public function productionUsage(?\Carbon\Carbon $month = null): array
    {
        $quota = $this->production_quota ?? [];
        if (! $quota) {
            return [];
        }

        $month ??= now();

        $usados = Task::where('client_id', $this->id)
            ->where('status', '!=', 'cancelado')
            ->whereRaw('date_trunc(?, COALESCE(approval_date, created_at)) = date_trunc(?, ?::date)',
                ['month', 'month', $month->toDateString()])
            ->selectRaw('task_type, count(*) as total')
            ->groupBy('task_type')
            ->pluck('total', 'task_type');

        $saida = [];
        foreach ($quota as $type => $qtd) {
            $qtd = (int) $qtd;
            if ($qtd <= 0) {
                continue;
            }
            $used = (int) ($usados[$type] ?? 0);
            $saida[] = [
                'type'  => $type,
                'label' => Task::$types[$type] ?? $type,
                'quota' => $qtd,
                'used'  => $used,
                'left'  => max(0, $qtd - $used),
            ];
        }

        return $saida;
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

    public function leadSources(): HasMany
    {
        return $this->hasMany(ClientLeadSource::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(ClientLead::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(ClientModule::class);
    }

    public function moduleStatus(string $moduleKey): string
    {
        return $this->modules()->where('module_key', $moduleKey)->value('status') ?? 'nao_contratado';
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
            'leads captados'               => $this->leads()->count(),
            'onboarding'                   => $this->onboarding ? 1 : 0,
        ];

        return array_filter($checks, fn ($count) => $count > 0);
    }
}

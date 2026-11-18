<?php

namespace App\Services\Demo;

use App\Models\AdAd;
use App\Models\AdAdset;
use App\Models\AdCampaign;
use App\Models\AdStatementDelivery;
use App\Models\CampaignInsight;
use App\Models\CampaignLog;
use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Models\ClientAdBillingDocument;
use App\Models\ClientAdBudget;
use App\Models\ClientContact;
use App\Models\ClientCredential;
use App\Models\ClientIntegration;
use App\Models\ClientLead;
use App\Models\ClientLeadOpportunity;
use App\Models\ClientLeadOpportunityNote;
use App\Models\ClientLeadSource;
use App\Models\ClientLink;
use App\Models\ClientModule;
use App\Models\Contact;
use App\Models\DeliverableFeedback;
use App\Models\LeadChannel;
use App\Models\MacroPlan;
use App\Models\MacroPlanAttachment;
use App\Models\MacroPlanComment;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectComment;
use App\Models\ProjectPhase;
use App\Models\ServiceConversation;
use App\Models\ServiceDiagnostic;
use App\Models\ServiceDiagnosticPersona;
use App\Models\ServiceDiagnosticRecommendation;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskApprovalRound;
use App\Models\TaskApprovalToken;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskExecutor;
use App\Models\TaskStatusTransition;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Monta o cliente fictício "Aurora Estruturas Metálicas" numa organização
 * própria (status "demo") pra gravar os vídeos do Portal. Tudo é relativo a
 * hoje: rodar de novo (demo:portal --fresh) atualiza prazos, semanas e métricas.
 *
 * Grava sem eventos de model (nada de automação, aviso ou WhatsApp) e sem
 * régua de lead (a IA de qualificação não pega esses leads).
 */
class PortalDemoBuilder
{
    public const ORG_SLUG = 'nonna-demo';

    private const ASSETS = 'resources/demo/portal';
    private const R2_DIR = 'demo/portal/aurora';

    private Organization $org;
    private Client $client;
    private Carbon $today;
    /** @var array<string, User> */
    private array $u = [];
    /** @var array<string, Contact> */
    private array $c = [];
    /** @var array<string, array{path: string, size: int, mime: string, name: string}> */
    private array $files = [];
    private array $campaigns = [];
    private array $channels = [];
    private array $sources = [];
    private ?Sprint $sprint = null;
    private ?Sprint $prevSprint = null;
    private ?Closure $log = null;
    private array $counts = [];

    public function organization(): ?Organization
    {
        return Organization::where('slug', self::ORG_SLUG)->first();
    }

    // ─────────────────────────────────────────────────────────────── build

    public function build(string $password, ?Closure $log = null): array
    {
        $this->log = $log;
        $this->today = today();
        mt_srand(20261118);

        $this->uploadAssets();

        Model::withoutEvents(function () use ($password) {
            DB::connection('pgsql')->transaction(function () use ($password) {
                $this->say('Organização, equipe e cliente');
                $this->organizationAndTeam();
                $this->clientAndContacts($password);
                $this->say('Contas de anúncio, campanhas e métricas diárias');
                $this->adsAndMetrics();
                $this->say('Planejamentos, projetos e tarefas');
                $this->plansProjectsTasks();
                $this->say('Chamados');
                $this->tickets();
                $this->say('Reuniões');
                $this->meetings();
                $this->say('Central de Leads');
                $this->leads();
                $this->say('Atendimento (WhatsApp) e diagnósticos');
                $this->service();
                $this->say('Prestação de contas');
                $this->billing();
            });
        });

        return [
            'logins' => [
                [$this->c['rodrigo']->email, $password, 'Diretor (contato principal)'],
                [$this->c['juliana']->email, $password, 'Marketing'],
                [$this->c['carlos']->email, $password, 'Comercial (leads)'],
            ],
            'counts' => $this->counts,
        ];
    }

    // ─────────────────────────────────────────────────────────────── arquivos

    private function uploadAssets(): void
    {
        $this->say('Enviando artes e documentos para o armazenamento');
        $disk = Storage::disk('r2');
        $vars = $this->periodVars();

        foreach (glob(base_path(self::ASSETS) . '/*') as $file) {
            $name = basename($file);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'pdf'  => 'application/pdf',
                'html' => 'text/html',
                default => 'application/octet-stream',
            };
            $body = file_get_contents($file);
            if ($ext === 'html') {
                $key  = str_contains($name, 'q3') ? 'q3' : 'q4';
                $body = strtr($body, $vars[$key]);
            }
            $path = self::R2_DIR . '/' . $name;
            $ok = $disk->put($path, $body, str_starts_with($mime, 'text/')
                ? ['ContentType' => $mime . '; charset=UTF-8']
                : ['ContentType' => $mime]);
            if (!$ok) {
                throw new \RuntimeException("Falha ao enviar {$name} para o R2.");
            }
            $this->files[pathinfo($name, PATHINFO_FILENAME)] = ['path' => $path, 'size' => strlen($body), 'mime' => $mime, 'name' => $name];
        }
    }

    /** Ciclo atual ≈ 45 dias atrás até ~45 dias à frente, em meses cheios. */
    private function cycles(): array
    {
        $q4s = $this->today->copy()->subDays(45)->startOfMonth();
        $q4e = $q4s->copy()->addMonthsNoOverflow(3)->subDay();
        $q3s = $q4s->copy()->subMonthsNoOverflow(3);
        $q3e = $q4s->copy()->subDay();

        return [$q3s, $q3e, $q4s, $q4e];
    }

    private function periodVars(): array
    {
        [$q3s, $q3e, $q4s, $q4e] = $this->cycles();
        $long  = fn (Carbon $s, Carbon $e) => Str::ucfirst($s->locale('pt_BR')->translatedFormat('F')) . ' a ' . $e->locale('pt_BR')->translatedFormat('F') . ' de ' . $e->year;
        $short = fn (Carbon $s, Carbon $e) => Str::ucfirst($s->locale('pt_BR')->translatedFormat('M')) . ' a ' . Str::ucfirst($e->locale('pt_BR')->translatedFormat('M')) . '/' . $e->year;
        $month = fn (Carbon $s, int $i) => Str::ucfirst($s->copy()->addMonthsNoOverflow($i)->locale('pt_BR')->translatedFormat('F'));

        return [
            'q3' => ['{{PERIODO}}' => $long($q3s, $q3e), '{{PERIODO_CURTO}}' => $short($q3s, $q3e)],
            'q4' => [
                '{{PERIODO}}' => $long($q4s, $q4e), '{{PERIODO_CURTO}}' => $short($q4s, $q4e),
                '{{DATAS}}' => $q4s->format('d/m') . ' a ' . $q4e->format('d/m/Y'),
                '{{M1}}' => $month($q4s, 0), '{{M2}}' => $month($q4s, 1), '{{M3}}' => $month($q4s, 2),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────── base

    private function organizationAndTeam(): void
    {
        $this->org = $this->mk(Organization::class, [
            'name'   => 'Nonna Demo (Portal de exemplo)',
            'slug'   => self::ORG_SLUG,
            'plan'   => 'enterprise',
            'status' => 'demo',
            'settings' => ['branding' => ['primary_color' => '#643B8E', 'secondary_color' => '#EE7919', 'logo_url' => null]],
        ]);

        $team = [
            'mariana'  => ['Mariana Costa', 'admin'],
            'patricia' => ['Patrícia Lima', 'member'],
            'lucas'    => ['Lucas Ferreira', 'member'],
            'bruna'    => ['Bruna Alves', 'member'],
            'thiago'   => ['Thiago Ramos', 'member'],
            'diego'    => ['Diego Souza', 'member'],
            'rafaela'  => ['Rafaela Nunes', 'member'],
        ];
        foreach ($team as $key => [$name, $role]) {
            $user = $this->mk(User::class, [
                'name' => $name,
                'email' => Str::slug($name, '.') . '@demo.nonna.invalid',
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
            ]);
            DB::connection('pgsql')->table('organization_users')->insert([
                'id' => (string) Str::orderedUuid(), 'organization_id' => $this->org->id, 'user_id' => $user->id,
                'role' => $role, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->u[$key] = $user;
        }
        $this->org->forceFill(['owner_user_id' => $this->u['mariana']->id])->save();

        // Super admins (Evandro) entram como donos: trocam pra cá no seletor do topo.
        foreach (User::withoutGlobalScopes()->where('is_super_admin', true)->pluck('id') as $adminId) {
            DB::connection('pgsql')->table('organization_users')->insert([
                'id' => (string) Str::orderedUuid(), 'organization_id' => $this->org->id, 'user_id' => $adminId,
                'role' => 'owner', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach ([['Site', 'site', 'purple'], ['Facebook/Instagram Lead Ads', 'facebook_lead_ad', 'blue'], ['WhatsApp', 'whatsapp', 'green']] as [$name, $kind, $color]) {
            $this->channels[$kind] = $this->mk(LeadChannel::class, ['organization_id' => $this->org->id, 'name' => $name, 'kind' => $kind, 'color' => $color, 'is_active' => true]);
        }

        $monday = $this->today->copy()->startOfWeek(Carbon::MONDAY);
        $this->prevSprint = $this->mk(Sprint::class, [
            'organization_id' => $this->org->id, 'title' => 'Sprint ' . $monday->copy()->subWeeks(2)->format('d/m') . ' a ' . $monday->copy()->subDay()->format('d/m'),
            'starts_at' => $monday->copy()->subWeeks(2), 'ends_at' => $monday->copy()->subDay(), 'status' => 'closed',
            'created_by' => $this->u['mariana']->id, 'locked_at' => $monday->copy()->subWeeks(2), 'locked_by' => $this->u['mariana']->id,
        ]);
        $this->sprint = $this->mk(Sprint::class, [
            'organization_id' => $this->org->id, 'title' => 'Sprint ' . $monday->format('d/m') . ' a ' . $monday->copy()->addDays(13)->format('d/m'),
            'starts_at' => $monday, 'ends_at' => $monday->copy()->addDays(13), 'status' => 'active',
            'created_by' => $this->u['mariana']->id, 'locked_at' => $monday, 'locked_by' => $this->u['mariana']->id,
        ]);
    }

    private function clientAndContacts(string $password): void
    {
        $this->client = $this->mk(Client::class, [
            'organization_id' => $this->org->id,
            'company_name' => 'Aurora Estruturas Metálicas Ltda',
            'nickname' => 'Aurora',
            'tax_id' => '12.345.678/0001-95',
            'website' => 'https://auroraestruturas.com.br',
            'segment' => 'Serviços B2B',
            'status' => 'active',
            'monthly_ad_budget' => 'R$ 9.000 / mês',
            'contracted_services' => ['trafego', 'social', 'site', 'seo', 'email', 'automacao'],
            'registration_completed_at' => $this->days(-140),
            'contact_email' => 'contato@auroraestruturas.com.br',
            'contact_phone' => '(49) 3000-0000',
            'address' => 'Rodovia SC-480, km 12, Distrito Industrial — Chapecó/SC',
            'zip_code' => '89800-000',
            'responsible_name' => 'Rodrigo Martini',
            'responsible_birthdate' => '1979-04-22',
            'responsible_cpf' => '000.000.000-00',
            'responsible_rg' => '0.000.000',
            'responsible_address' => 'Rua Exemplo, 100 — Chapecó/SC',
            'responsible_marital_status' => 'casado',
            'payment_method' => 'boleto',
            'billing_day' => 10,
            'billing_email' => 'financeiro@auroraestruturas.com.br',
            'billing_whatsapp' => '(49) 90000-0010',
            'notes' => 'Cliente fictício usado nos vídeos do Portal. Não é uma empresa real.',
            'briefing' => "Fabricante de estruturas metálicas (galpões, coberturas e mezaninos) no Oeste de SC, atendendo SC, RS e PR. Fábrica própria com corte a plasma CNC. Diferencial: prazo em contrato. Cliente ideal: indústria, agroindústria e logística com obra prevista em até 12 meses.",
            'creative_lead_id' => $this->u['lucas']->id,
            'production_quota' => ['criacao' => 12, 'social' => 8, 'web' => 3, 'trafego' => 4, 'email' => 2],
            'average_ticket' => 420000,
        ]);

        $people = [
            'rodrigo' => ['Rodrigo Martini', 'Diretor Geral', 'rodrigo.martini', '(49) 90000-0001', true],
            'juliana' => ['Juliana Prado', 'Coordenadora de Marketing', 'juliana.prado', '(49) 90000-0002', false],
            'carlos'  => ['Carlos Becker', 'Gerente Comercial', 'carlos.becker', '(49) 90000-0003', false],
        ];
        foreach ($people as $key => [$name, $job, $mail, $phone, $primary]) {
            $contact = $this->mk(Contact::class, [
                'organization_id' => $this->org->id, 'name' => $name, 'email' => "{$mail}@aurorademo.com.br",
                'phone' => $phone, 'whatsapp' => $phone, 'job_title' => $job, 'company_name' => 'Aurora Estruturas Metálicas',
                'source' => 'indicacao', 'status' => 'ativo', 'created_by' => $this->u['mariana']->id,
                'password' => Hash::make($password), 'portal_last_login_at' => $this->days(-3, '08:12'),
            ]);
            $this->mk(ClientContact::class, [
                'client_id' => $this->client->id, 'contact_id' => $contact->id, 'role' => $job,
                'is_primary' => $primary, 'portal_access_enabled' => true,
            ]);
            $this->c[$key] = $contact;
        }

        foreach (array_keys(ClientModule::$modules) as $key) {
            $this->mk(ClientModule::class, [
                'client_id' => $this->client->id, 'module_key' => $key, 'status' => 'ativo',
                'enabled_at' => $this->days(-120), 'enabled_by' => $this->u['mariana']->id,
                'settings' => $key === 'central_leads' ? ['hide_assigned' => false] : null,
            ]);
        }

        $creds = [
            ['site', 'https://auroraestruturas.com.br/wp-admin', 'nonna.admin', 'Exemplo#2026', 'Acesso administrador do WordPress.'],
            ['instagram', 'https://instagram.com/auroraestruturas', '@auroraestruturas', 'Exemplo#2026', 'Login com verificação em duas etapas no celular da Juliana.'],
            ['google_meu_negocio', 'https://business.google.com', 'marketing@auroraestruturas.com.br', 'Exemplo#2026', null],
            ['meta_bm', 'https://business.facebook.com', 'Acesso por convite', null, 'A Nonna tem acesso de parceiro ao Business Manager.'],
        ];
        foreach ($creds as [$platform, $url, $user, $pass, $notes]) {
            $this->mk(ClientCredential::class, [
                'client_id' => $this->client->id, 'platform' => $platform, 'access_url' => $url, 'username' => $user,
                'password' => $pass, 'notes' => $notes, 'created_by' => $this->u['mariana']->id,
            ]);
        }

        foreach ([['drive', 'Pasta da Aurora no Drive', 'https://drive.google.com/'], ['contrato', 'Contrato de serviços', 'https://drive.google.com/'], ['apresentacao', 'Apresentação institucional', 'https://drive.google.com/']] as [$type, $label, $url]) {
            $this->mk(ClientLink::class, ['client_id' => $this->client->id, 'type' => $type, 'label' => $label, 'url' => $url, 'created_by' => $this->u['mariana']->id]);
        }
    }

    // ─────────────────────────────────────────────────────────────── anúncios

    private function adsAndMetrics(): void
    {
        [$q3s] = $this->cycles();

        $meta = $this->mk(ClientAdAccount::class, [
            'client_id' => $this->client->id, 'platform' => 'meta_ads', 'account_id' => 'act_000000000001',
            'account_name' => 'Aurora Estruturas — Meta', 'status' => 'ativo', 'payment_method' => 'pix',
            'balance' => 2140.50, 'balance_source' => 'manual', 'balance_synced_at' => $this->days(-1, '06:00'),
            'budget_status' => 'creditos_ativos', 'responsible_user_id' => $this->u['thiago']->id,
            'target_cost_per_result' => 60, 'created_by' => $this->u['thiago']->id,
        ]);
        $google = $this->mk(ClientAdAccount::class, [
            'client_id' => $this->client->id, 'platform' => 'google_ads', 'account_id' => '000-000-0001',
            'account_name' => 'Aurora Estruturas — Google', 'status' => 'ativo', 'payment_method' => 'boleto',
            'balance' => 1380.00, 'balance_source' => 'manual', 'balance_synced_at' => $this->days(-1, '06:00'),
            'budget_status' => 'creditos_ativos', 'responsible_user_id' => $this->u['thiago']->id,
            'target_cost_per_result' => 75, 'created_by' => $this->u['thiago']->id,
        ]);
        $this->mk(ClientAdBudget::class, ['client_id' => $this->client->id, 'monthly_budget' => 9000, 'start_date' => $q3s, 'notes' => 'R$ 5.000 Meta + R$ 4.000 Google', 'created_by' => $this->u['thiago']->id]);

        $t = $this->today;
        // [chave, conta, plataforma, nome, objetivo, início (dias), fim (dias|null), status, gasto/dia, custo por resultado|null, criativos]
        $defs = [
            ['c1', $meta, 'meta', '[LEADS][CAD] Obra Pronta — Galpões Sul', 'OUTCOME_LEADS', -48, null, 'active', 112, 54, ['ad-galpao-sob-medida', 'obra-pronta-kv', 'story-orcamento', 'carrossel-erros-1']],
            ['c2', $meta, 'meta', '[REMARKETING] Visitantes do site — Orçamento', 'OUTCOME_LEADS', -100, null, 'active', 26, 68, ['ad-mezanino', 'obra-entregue-chapeco', 'metalica-x-concreto', 'bastidores-plasma']],
            ['c3', $meta, 'meta', '[ALCANCE] Vídeo Institucional — Feito pra durar', 'OUTCOME_AWARENESS', -14, null, 'active', 30, null, ['thumb-institucional', 'thumb-institucional', 'bastidores-plasma', 'obra-entregue-chapeco']],
            ['c4', $meta, 'meta', '[LEADS] Galpão em 90 dias (ciclo anterior)', 'OUTCOME_LEADS', -125, -49, 'paused', 118, 62, ['obra-pronta-kv-v1', 'ad-galpao-sob-medida', 'carrossel-erros-1', 'ad-mezanino']],
            ['g1', $google, 'google', '[PESQUISA] Galpão e Estrutura Metálica', 'SEARCH', -125, null, 'active', 86, 71, ['ad-display-google', 'ad-display-google', 'ad-display-google', 'ad-display-google']],
            ['g2', $google, 'google', '[PESQUISA] Mezanino Metálico', 'SEARCH', -78, null, 'active', 31, 88, ['ad-display-google', 'ad-display-google', 'ad-display-google', 'ad-display-google']],
            ['g3', $google, 'google', '[DISPLAY] Remarketing Obra Pronta', 'DISPLAY', -39, null, 'active', 18, 140, ['ad-display-google', 'banner-catalogo', 'ad-display-google', 'banner-catalogo']],
        ];

        $rows = [];
        $monthly = []; // [conta][AAAA-MM] => gasto
        $n = 0;
        foreach ($defs as [$key, $account, $platform, $name, $objective, $from, $to, $status, $daily, $cpr, $creatives]) {
            $n++;
            $ext = $platform === 'meta' ? '1202600000000' . str_pad((string) ($n * 100), 5, '0', STR_PAD_LEFT) : '2260000' . str_pad((string) ($n * 100), 4, '0', STR_PAD_LEFT);
            $campaign = $this->mk(AdCampaign::class, [
                'organization_id' => $this->org->id, 'client_ad_account_id' => $account->id, 'platform' => $platform,
                'external_id' => $ext, 'name' => $name, 'status' => $status, 'objective' => $objective,
                'start_date' => $t->copy()->addDays($from), 'end_date' => $to !== null ? $t->copy()->addDays($to) : null,
                'raw_data' => ['id' => $ext, 'name' => $name, 'status' => strtoupper($status), 'demo' => true],
                'last_synced_at' => $this->days(-1, '05:31'),
                'management_status' => $status === 'paused' ? 'encerrada' : ($key === 'c1' ? 'em_otimizacao' : 'em_veiculacao'),
                'optimization_tier' => 'normal', 'last_optimized_at' => $this->days(-3, '15:10'),
                'target_cost_per_result' => $cpr,
                'description' => $this->campaignDescription($key),
            ]);
            $this->campaigns[$key] = ['model' => $campaign, 'ads' => []];

            $adsetNames = $platform === 'meta'
                ? ['Público semelhante — clientes', 'Interesses: indústria e logística']
                : ['Grupo: galpão', 'Grupo: estrutura metálica'];
            if ($key === 'g2') {
                $adsetNames = ['Grupo: mezanino', 'Grupo: mezanino industrial'];
            }
            $adEntities = [];
            foreach ($adsetNames as $si => $setName) {
                $setExt = $ext . ($si + 1);
                $adset = $this->mk(AdAdset::class, [
                    'organization_id' => $this->org->id, 'ad_campaign_id' => $campaign->id, 'platform' => $platform,
                    'external_id' => $setExt, 'campaign_external_id' => $ext, 'name' => $setName, 'status' => $status,
                    'daily_budget' => round($daily * ($si ? 0.4 : 0.6)), 'raw_data' => ['demo' => true], 'last_synced_at' => $this->days(-1, '05:31'),
                ]);
                foreach ([0, 1] as $ai) {
                    $art = $creatives[$si * 2 + $ai];
                    $adExt = $setExt . ($ai + 1);
                    $ad = $this->mk(AdAd::class, [
                        'organization_id' => $this->org->id, 'ad_adset_id' => $adset->id, 'platform' => $platform,
                        'external_id' => $adExt, 'adset_external_id' => $setExt,
                        'name' => $this->adName($art, $ai), 'status' => $status,
                        'creative_type' => str_starts_with($art, 'thumb') ? 'video' : 'image',
                        'creative_url' => $this->thumbDataUri($art), 'raw_data' => ['demo' => true],
                        'last_synced_at' => $this->days(-1, '05:31'),
                    ]);
                    $this->campaigns[$key]['ads'][] = $ad;
                    $adEntities[] = [$adset, $ad, ($si ? 0.4 : 0.6) * ($ai ? 0.45 : 0.55)];
                }
            }

            // Métricas diárias (campanha + conjuntos + anúncios).
            $end = $to !== null ? $t->copy()->addDays($to) : $t->copy()->subDay();
            for ($day = $t->copy()->addDays($from); $day->lte($end); $day->addDay()) {
                $weekday = [0.68, 1, 1.05, 1.04, 1.02, 0.97, 0.8][$day->dayOfWeek];
                $ramp = min(1, 0.55 + (abs($day->diffInDays($t->copy()->addDays($from))) / 20));
                $spend = round($daily * $weekday * $ramp * (0.85 + mt_rand(0, 30) / 100), 2);
                $m = $this->dayMetrics($platform, $spend, $cpr);
                $monthly[$account->id][$day->format('Y-m')] = ($monthly[$account->id][$day->format('Y-m')] ?? 0) + $spend;

                $rows[] = $this->snapshot($account, 'campaign', $ext, $name, null, $platform, $day, $m);
                $bySet = [];
                foreach ($adEntities as [$adset, $ad, $w]) {
                    $part = $this->split($m, $w);
                    $rows[] = $this->snapshot($account, 'ad', $ad->external_id, $ad->name, $adset->external_id, $platform, $day, $part);
                    $bySet[$adset->external_id]['m'] = isset($bySet[$adset->external_id]) ? $this->sum($bySet[$adset->external_id]['m'], $part) : $part;
                    $bySet[$adset->external_id]['name'] = $adset->name;
                }
                foreach ($bySet as $setExt => $s) {
                    $rows[] = $this->snapshot($account, 'adset', $setExt, $s['name'], $ext, $platform, $day, $s['m']);
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::connection('pgsql')->table('ad_daily_snapshots')->insert($chunk);
        }
        $this->counts['Métricas diárias de anúncios'] = count($rows);
        $this->accounts = ['meta' => $meta, 'google' => $google, 'monthly' => $monthly];

        // Histórico de otimizações (aparece no detalhe da campanha).
        $logs = [
            ['c1', -40, 'Campanha publicada com 2 públicos e 4 anúncios. Formulário pergunta área, cidade e prazo da obra.'],
            ['c1', -27, 'Pausamos o anúncio com custo por lead acima da meta e redistribuímos a verba para o “Galpão sob medida”.'],
            ['c1', -13, 'Público semelhante ampliado de 1% para 3% depois de 30 leads bons na Central.'],
            ['c1', -3,  'Novo criativo (story “Orçamento em 48 horas”) entrou no conjunto de interesses.'],
            ['c2', -20, 'Janela de remarketing reduzida de 90 para 30 dias: leads mais quentes.'],
            ['c3', -12, 'Vídeo institucional no ar, otimizado para visualização de 15 segundos.'],
            ['g1', -35, 'Palavras negativas adicionadas: “aluguel”, “usado”, “curso”, “emprego”.'],
            ['g1', -15, 'Lance ajustado nas cidades com mais obras fechadas (Chapecó, Concórdia e Passo Fundo).'],
            ['g2', -9,  'Anúncio novo destacando instalação em 15 dias.'],
        ];
        foreach ($logs as [$key, $d, $text]) {
            $camp = $this->campaigns[$key]['model'];
            $this->mk(CampaignLog::class, [
                'organization_id' => $this->org->id, 'client_ad_account_id' => $camp->client_ad_account_id,
                'entity_level' => 'campaign', 'entity_id' => $camp->external_id, 'entity_name' => $camp->name,
                'platform' => $camp->platform, 'logged_by' => $this->u['thiago']->id, 'type' => 'otimizacao',
                'description' => $text, 'created_at' => $this->days($d, '16:20'), 'updated_at' => $this->days($d, '16:20'),
            ]);
        }

        // Análises da IA visíveis ao cliente.
        $insights = [
            ['c1', 'lead_funnel', 'info', 'novo', -2, 'Obra Pronta: 1 em cada 3 leads tem obra prevista',
             'Nas últimas duas semanas a campanha trouxe 31 contatos e 11 têm o perfil que a Aurora procura: empresa com obra prevista em até 12 meses e área acima de 600 m². O custo por lead bom ficou em R$ 152, abaixo da meta de R$ 180. Vamos colocar mais verba no público semelhante, que traz os melhores contatos, e testar um anúncio novo com obra entregue.',
             ['leads' => 31, 'good' => 11, 'cost_per_good' => 152.4]],
            ['g1', 'cpa_spike', 'atencao', 'resolvido', -16, 'Custo por resultado subiu no Google',
             'O custo por contato da campanha de pesquisa subiu 28% numa semana por causa de buscas por “aluguel de galpão”, que não interessam à Aurora. A equipe já bloqueou essas palavras e o custo voltou ao normal.',
             ['cpa_now' => 92.1, 'cpa_before' => 71.8]],
            ['c3', 'budget_overrun', 'info', 'lido', -6, 'Vídeo institucional gastando um pouco acima do previsto',
             'A campanha do vídeo institucional está entregando bem e gastou 12% acima do previsto para a semana. Mantivemos assim porque o custo por visualização está baixo; no fim do mês a verba é compensada nas outras campanhas.',
             ['pct_used' => 112]],
            ['g2', 'cost_per_result_above_target', 'atencao', 'novo', -1, 'Mezanino: custo por contato acima da meta',
             'A campanha de mezanino está com custo por contato de R$ 104, acima da meta de R$ 88. Os contatos que chegam são bons, então vamos ajustar os lances por horário antes de mexer na verba.',
             ['cpa' => 104.2, 'target' => 88]],
        ];
        foreach ($insights as [$key, $kind, $sev, $status, $d, $title, $text, $metrics]) {
            $camp = $this->campaigns[$key]['model'];
            $this->mk(CampaignInsight::class, [
                'organization_id' => $this->org->id, 'client_id' => $this->client->id,
                'client_ad_account_id' => $camp->client_ad_account_id, 'ad_campaign_id' => $camp->id,
                'kind' => $kind, 'severity' => $sev, 'status' => $status, 'title' => $title,
                'summary' => $text, 'client_summary' => $text, 'metrics_snapshot' => $metrics,
                'generated_at' => $this->days($d, '08:05'),
                'resolved_at' => $status === 'resolvido' ? $this->days($d + 2, '11:00') : null,
                'resolved_by' => $status === 'resolvido' ? $this->u['thiago']->id : null,
            ]);
        }
    }

    private array $accounts = [];

    private function campaignDescription(string $key): string
    {
        return [
            'c1' => 'Campanha principal do ciclo: captação de orçamentos de galpões com formulário qualificado.',
            'c2' => 'Remarketing para quem visitou o site e não pediu orçamento.',
            'c3' => 'Alcance do vídeo institucional “Feito pra durar” nas cidades atendidas.',
            'c4' => 'Campanha do ciclo anterior, encerrada e evoluída para a Obra Pronta.',
            'g1' => 'Pesquisa no Google por galpão e estrutura metálica nas regiões atendidas.',
            'g2' => 'Pesquisa no Google por mezanino metálico.',
            'g3' => 'Banners para quem já visitou a página de orçamento.',
        ][$key];
    }

    private function adName(string $art, int $i): string
    {
        $label = [
            'ad-galpao-sob-medida' => 'Galpão sob medida', 'obra-pronta-kv' => 'Seu galpão em 90 dias',
            'story-orcamento' => 'Story orçamento em 48h', 'carrossel-erros-1' => 'Carrossel 5 erros',
            'ad-mezanino' => 'Mezanino em 15 dias', 'obra-entregue-chapeco' => 'Obra entregue Chapecó',
            'metalica-x-concreto' => 'Metálica x concreto', 'bastidores-plasma' => 'Bastidores plasma',
            'thumb-institucional' => 'Vídeo institucional', 'obra-pronta-kv-v1' => 'Galpão em 90 dias',
            'ad-display-google' => 'Anúncio de texto', 'banner-catalogo' => 'Banner catálogo',
        ][$art] ?? $art;

        return '[' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) . '] ' . $label;
    }

    private function dayMetrics(string $platform, float $spend, ?float $cpr): array
    {
        if ($platform === 'meta') {
            $cpm = 22 + mt_rand(0, 120) / 10;
            $impressions = (int) round($spend / $cpm * 1000);
            $clicks = (int) round($impressions * (0.011 + mt_rand(0, 8) / 1000));
        } else {
            $cpc = 2.6 + mt_rand(0, 120) / 100;
            $clicks = (int) max(1, round($spend / $cpc));
            $impressions = (int) round($clicks / (0.04 + mt_rand(0, 30) / 1000));
        }
        $conv = $cpr ? $this->roundRandom($spend / ($cpr * (0.8 + mt_rand(0, 40) / 100))) : 0;

        return ['spend' => $spend, 'impressions' => $impressions, 'clicks' => $clicks, 'conversions' => $conv, 'reach' => (int) round($impressions / 1.6)];
    }

    private function split(array $m, float $w): array
    {
        return [
            'spend' => round($m['spend'] * $w, 2),
            'impressions' => (int) round($m['impressions'] * $w),
            'clicks' => (int) round($m['clicks'] * $w),
            'conversions' => $this->roundRandom($m['conversions'] * $w),
            'reach' => (int) round($m['reach'] * $w),
        ];
    }

    private function sum(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            $a[$k] = ($a[$k] ?? 0) + $v;
        }

        return $a;
    }

    private function snapshot(ClientAdAccount $account, string $level, string $id, string $name, ?string $parent, string $platform, Carbon $day, array $m): array
    {
        $now = now();

        return [
            'id' => (string) Str::orderedUuid(),
            'organization_id' => $this->org->id,
            'client_ad_account_id' => $account->id,
            'entity_level' => $level,
            'entity_id' => $id,
            'entity_name' => $name,
            'parent_entity_id' => $parent,
            'platform' => $platform,
            'snapshot_date' => $day->toDateString(),
            'spend' => $m['spend'],
            'revenue' => 0,
            'impressions' => $m['impressions'],
            'clicks' => $m['clicks'],
            'conversions' => $m['conversions'],
            'reach' => $m['reach'],
            'cpm' => $m['impressions'] ? round($m['spend'] / $m['impressions'] * 1000, 4) : null,
            'cpc' => $m['clicks'] ? round($m['spend'] / $m['clicks'], 4) : null,
            'ctr' => $m['impressions'] ? round($m['clicks'] / $m['impressions'] * 100, 4) : null,
            'cpa' => $m['conversions'] ? round($m['spend'] / $m['conversions'], 4) : null,
            'roas' => 0,
            'raw_data' => json_encode(['demo' => true]),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function roundRandom(float $x): int
    {
        $base = (int) floor($x);

        return $base + ((mt_rand(0, 999) / 1000) < ($x - $base) ? 1 : 0);
    }

    private function thumbDataUri(string $art): string
    {
        $src = imagecreatefromjpeg(base_path(self::ASSETS . "/{$art}.jpg"));
        $w = imagesx($src);
        $h = imagesy($src);
        $nw = 360;
        $nh = (int) round($h * $nw / $w);
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($dst, null, 72);

        return 'data:image/jpeg;base64,' . base64_encode((string) ob_get_clean());
    }

    // ─────────────────────────────────────────────────────────────── planejamentos

    private function plansProjectsTasks(): void
    {
        [$q3s, $q3e, $q4s, $q4e] = $this->cycles();
        $m = $this->u['mariana'];

        $q3 = $this->mk(MacroPlan::class, [
            'organization_id' => $this->org->id, 'client_id' => $this->client->id, 'responsible_id' => $this->u['patricia']->id,
            'created_by' => $m->id, 'title' => MacroPlan::standardTitleFor($this->client, $q3s, $q3e),
            'period_start' => $q3s, 'period_end' => $q3e, 'status' => 'concluido', 'version' => 'v1.0',
            'focus' => 'Colocar a casa em ordem: site novo, medição de resultados e a primeira campanha de captação.',
            'disciplines' => ['web', 'criacao', 'trafego', 'setup', 'estrategia'],
            'launched_at' => $q3s->copy()->addDays(3), 'created_at' => $q3s->copy()->subDays(6),
        ]);
        $q4 = $this->mk(MacroPlan::class, [
            'organization_id' => $this->org->id, 'client_id' => $this->client->id, 'responsible_id' => $this->u['patricia']->id,
            'created_by' => $m->id, 'title' => MacroPlan::standardTitleFor($this->client, $q4s, $q4e),
            'period_start' => $q4s, 'period_end' => $q4e, 'status' => 'em_execucao', 'version' => 'v2.0',
            'focus' => 'Transformar a procura por galpões em orçamentos qualificados e mostrar a fábrica por dentro.',
            'disciplines' => ['criacao', 'social', 'trafego', 'web', 'email', 'estrategia'],
            'bloco1' => [
                'foco_principal' => 'Transformar a procura por galpões em orçamentos qualificados e mostrar a fábrica por dentro.',
                'contexto_anterior' => 'Site novo no ar e 214 contatos no ciclo anterior, 61 com obra prevista. O gargalo agora é separar quem tem obra de verdade e responder rápido.',
                'verba_total' => '9.000', 'meta_pct' => '55', 'google_pct' => '45',
                'verba_obs' => 'R$ 5.000/mês no Meta e R$ 4.000/mês no Google.',
                'kpis' => [
                    ['label' => 'KPI Principal', 'title' => 'Orçamentos qualificados', 'desc' => '90 no trimestre.'],
                    ['label' => 'KPI de Tráfego', 'title' => 'Custo por lead bom', 'desc' => 'Até R$ 180.'],
                    ['label' => 'KPI de Atendimento', 'title' => 'Primeira resposta no WhatsApp', 'desc' => 'Menos de 30 minutos.'],
                    ['label' => 'KPI de Negócio', 'title' => 'Obras fechadas vindas do digital', 'desc' => '6 no trimestre.'],
                ],
            ],
            'bloco2' => ['pilares' => [
                ['titulo' => 'Prova', 'desc' => 'Obras entregues com dias de obra e m² cobertos.'],
                ['titulo' => 'Bastidores', 'desc' => 'A fábrica por dentro: corte, solda, pintura e montagem.'],
                ['titulo' => 'Educação', 'desc' => 'Conteúdo técnico para engenheiros e compradores.'],
            ]],
            'launched_at' => $q4s->copy()->addDays(4), 'created_at' => $q4s->copy()->subDays(8),
        ]);
        $this->counts['Planejamentos'] = 2;

        foreach ([[$q3, 'plan-q3'], [$q4, 'plan-q4']] as [$plan, $file]) {
            $att = $this->mk(MacroPlanAttachment::class, [
                'macro_plan_id' => $plan->id, 'filename' => 'Planejamento Aurora.html', 'disk_path' => $this->files[$file]['path'],
                'disk' => 'r2', 'mime_type' => 'text/html', 'size' => $this->files[$file]['size'], 'uploaded_by' => $this->u['patricia']->id,
            ]);
            $plan->forceFill(['document_attachment_id' => $att->id])->save();
        }

        $this->mk(MacroPlanComment::class, [
            'organization_id' => $this->org->id, 'macro_plan_id' => $q4->id, 'user_id' => $this->u['patricia']->id,
            'body' => '<p>Planejamento aprovado na reunião de abertura do ciclo. Qualquer ajuste de prioridade, é só comentar aqui.</p>',
            'visible_to_client' => true, 'created_at' => $q4s->copy()->addDays(2)->setTime(17, 40), 'updated_at' => $q4s->copy()->addDays(2)->setTime(17, 40),
        ]);

        // Projetos do ciclo anterior (concluídos).
        $site = $this->project($q3, 1, 'projeto', 'Novo Site Institucional', 'concluido', ['web', 'criacao', 'seo'],
            'Site novo, rápido e pensado para gerar pedido de orçamento, com páginas por tipo de obra e portfólio de obras entregues.', $q3s->copy()->addDays(5), $q3s->copy()->addDays(58));
        $g90 = $this->project($q3, 2, 'campanha', 'Campanha Galpão em 90 dias', 'concluido', ['trafego', 'criacao'],
            'Primeira campanha de captação de orçamentos no Meta e no Google.', $q3s->copy()->addDays(20), $q3e);
        $setup = $this->project($q3, 3, 'projeto', 'Medição e Central de Leads', 'concluido', ['setup', 'web'],
            'Pixel, Google Analytics e Central de Leads ligados ao site, ao Facebook e ao WhatsApp.', $q3s->copy()->addDays(10), $q3s->copy()->addDays(40));

        $done = fn (int $d) => $q3s->copy()->addDays($d);
        foreach ([
            [$site, 'Wireframe das páginas principais', 'web', 'diego', 12], [$site, 'Layout da home e páginas de obra', 'criacao', 'lucas', 25],
            [$site, 'Desenvolvimento do site em WordPress', 'web', 'diego', 48], [$site, 'Textos com foco em busca (SEO)', 'seo', 'patricia', 40],
            [$site, 'Publicação e redirecionamento do domínio', 'web', 'diego', 58],
            [$g90, 'Criativos da campanha Galpão em 90 dias', 'criacao', 'lucas', 24], [$g90, 'Configuração das campanhas no Meta e no Google', 'trafego', 'thiago', 27],
            [$g90, 'Relatório final da campanha', 'trafego', 'thiago', 88],
            [$setup, 'Instalação do Pixel e do Google Analytics', 'setup', 'diego', 18], [$setup, 'Ligar formulários do site à Central de Leads', 'setup', 'diego', 30],
            [$setup, 'Treinamento do comercial na Central de Leads', 'reunioes', 'mariana', 38],
        ] as [$proj, $title, $type, $who, $d]) {
            $this->task(['project' => $proj, 'title' => $title, 'type' => $type, 'status' => 'concluido', 'who' => $who,
                'due' => $done($d), 'created' => $done($d - 10), 'done_at' => $done($d)]);
        }

        // Ciclo atual.
        $obra = $this->project($q4, 1, 'campanha', 'Obra Pronta — galpão em 90 dias', 'em_execucao', ['criacao', 'trafego', 'social', 'web'],
            'Campanha principal do ciclo: captar orçamentos de galpões, coberturas e mezaninos com a promessa de prazo em contrato.', $q4s->copy()->addDays(6), $q4e, [
                'brief_status' => 'detalhado', 'budget' => 6000, 'pieces_due_date' => $q4s->copy()->addDays(3),
                'big_idea_titulo' => 'Prazo é a nossa promessa.',
                'big_idea_manifesto' => 'Quem vai construir um galpão tem medo de obra atrasada. A Aurora responde com prova: obra entregue, dias contados e prazo no contrato.',
                'racional_estrategico' => 'O comprador industrial compara preço, mas decide por segurança. Mostrar prazo cumprido reduz o risco percebido.',
                'frase_voz' => 'Direta, técnica e confiante.',
                'assinatura' => 'Aurora. Feito pra durar.',
                'mecanica' => [
                    ['titulo' => 'Anúncio', 'texto' => 'Peças com obras reais e o número de dias de cada uma.'],
                    ['titulo' => 'Página de orçamento', 'texto' => 'Formulário curto: área, cidade e prazo da obra.'],
                    ['titulo' => 'Central de Leads', 'texto' => 'Cada contato chega com a origem e a avaliação da IA.'],
                    ['titulo' => 'Comercial', 'texto' => 'Resposta em até 30 minutos e visita técnica para os leads bons.'],
                ],
                'client_description' => 'A campanha principal do trimestre. Aqui ficam todas as peças: o que já foi aprovado e o que está esperando a sua resposta.',
                'tags' => ['Campanha principal', 'Meta Ads', 'Google Ads'],
            ]);
        $bast = $this->project($q4, 2, 'campanha', 'Bastidores da Fábrica', 'em_execucao', ['social', 'criacao'],
            'Série de conteúdo mostrando o processo de fabricação: corte, solda, pintura e montagem.', $q4s->copy()->addDays(8), $q4e,
            ['client_description' => 'Conteúdo do Instagram mostrando a fábrica por dentro.']);
        $cat = $this->project($q4, 3, 'projeto', 'Catálogo Técnico Digital 2027', 'em_execucao', ['criacao', 'web', 'estrategia'],
            'Catálogo em PDF e página de download no site com perfis, vãos e tabela de carga.', $q4s->copy()->addDays(15), $q4e->copy()->subDays(20));
        $video = $this->project($q4, 4, 'projeto', 'Vídeo Institucional “Feito pra durar”', 'em_execucao', ['criacao'],
            'Filme de 60 segundos com versões de 15 segundos para anúncios e Reels.', $q4s->copy()->addDays(5), $q4e->copy()->subDays(25));
        $news = $this->project($q4, 5, 'projeto', 'Newsletter da Engenharia', 'aprovacao', ['email', 'criacao'],
            'E-mail mensal para engenheiros, arquitetos e compradores da base.', $this->today->copy()->addDays(5), $q4e);
        $this->counts['Projetos e campanhas'] = 8;

        $docAtt = $this->mk(ProjectAttachment::class, [
            'project_id' => $obra->id, 'filename' => 'Campanha Obra Pronta.html', 'disk_path' => $this->files['campanha-obra-pronta']['path'],
            'disk' => 'r2', 'mime_type' => 'text/html', 'size' => $this->files['campanha-obra-pronta']['size'], 'uploaded_by' => $this->u['patricia']->id,
        ]);
        $obra->forceFill(['document_attachment_id' => $docAtt->id])->save();
        $this->campaigns['c1']['model']->forceFill(['project_id' => $obra->id])->save();
        $this->campaigns['c4']['model']->forceFill(['project_id' => $g90->id])->save();

        // Fases (linha do tempo).
        $this->phases($obra, [
            ['Planejamento e peças', $q4s, $q4s->copy()->addDays(5), false],
            ['Campanha no ar', $q4s->copy()->addDays(6), $q4e, false],
            ['Revisão do resultado', $q4e, $q4e, true],
        ]);
        $this->phases($cat, [
            ['Conteúdo técnico', $q4s->copy()->addDays(15), $this->today->copy()->subDays(10), false],
            ['Diagramação', $this->today->copy()->subDays(9), $this->today->copy()->addDays(3), false],
            ['Página de download', $this->today->copy()->addDays(1), $this->today->copy()->addDays(9), false],
            ['Lançamento do catálogo', $this->today->copy()->addDays(12), $this->today->copy()->addDays(12), true],
        ]);
        $this->phases($video, [
            ['Roteiro', $q4s->copy()->addDays(5), $q4s->copy()->addDays(16), false],
            ['Gravação', $q4s->copy()->addDays(20), $q4s->copy()->addDays(22), false],
            ['Edição', $q4s->copy()->addDays(30), $this->today->copy()->addDays(4), false],
            ['Entrega final', $this->today->copy()->addDays(10), $this->today->copy()->addDays(10), true],
        ]);

        // ── Obra Pronta
        $kv = $this->task(['project' => $obra, 'title' => 'Key visual da campanha Obra Pronta', 'type' => 'criacao', 'status' => 'concluido', 'who' => 'lucas',
            'due' => $this->d(-44), 'created' => $this->d(-50), 'done_at' => $this->d(-41), 'situation' => 'Aprovado',
            'description' => 'Peça-mãe da campanha: título “Seu galpão pronto em 90 dias”, obra em destaque e chamada para orçamento.']);
        $this->rounds($kv, [
            ['files' => ['obra-pronta-kv-v1'], 'status' => 'changes_requested', 'sent' => -47, 'resolved' => -46,
             'caption' => 'Primeira versão do key visual.', 'feedback' => 'Gostamos do título, mas o amarelo de fundo ficou forte demais. Dá pra usar o azul da marca e manter o amarelo só no destaque?'],
            ['files' => ['obra-pronta-kv'], 'status' => 'approved', 'sent' => -43, 'resolved' => -42, 'caption' => 'Versão 2 com o fundo azul e o amarelo só no destaque.'],
        ]);
        $obra->forceFill(['highlight_task_id' => $kv->id])->save();

        $car = $this->task(['project' => $obra, 'title' => 'Carrossel “5 erros que encarecem o seu galpão”', 'type' => 'social', 'status' => 'concluido', 'who' => 'bruna',
            'due' => $this->d(-30), 'created' => $this->d(-38), 'done_at' => $this->d(-28), 'publish' => $this->d(-28), 'situation' => 'Publicado / No Ar']);
        $this->rounds($car, [['files' => ['carrossel-erros-1', 'carrossel-erros-2'], 'status' => 'approved', 'sent' => -33, 'resolved' => -32,
            'caption' => "Construir um galpão é investimento alto — e alguns erros no começo custam caro no final. 👷‍♂️\n\nArraste para ver os 5 mais comuns e como a Aurora evita cada um.\n\n#galpao #estruturametalica #construcaoindustrial"]]);

        foreach ([
            ['Anúncio “Galpão sob medida”', 'ad-galpao-sob-medida', -36, 'Anúncio quadrado para o público de indústria e logística.'],
            ['Banner display para o Google', 'ad-display-google', -34, 'Banner 1200×628 para a campanha de remarketing.'],
        ] as [$title, $art, $d, $cap]) {
            $t = $this->task(['project' => $obra, 'title' => $title, 'type' => 'criacao', 'status' => 'concluido', 'who' => 'lucas',
                'due' => $this->d($d), 'created' => $this->d($d - 6), 'done_at' => $this->d($d + 2), 'situation' => 'Aprovado', 'destination' => 'campanhas_patrocinadas']);
            $this->rounds($t, [['files' => [$art], 'status' => 'approved', 'sent' => $d - 1, 'resolved' => $d, 'caption' => $cap]]);
        }

        $story = $this->task(['project' => $obra, 'title' => 'Story “Orçamento em 48 horas”', 'type' => 'criacao', 'status' => 'aprovacao', 'who' => 'lucas',
            'due' => $this->d(1), 'created' => $this->d(-6), 'situation' => 'Em Aprovação Cliente', 'destination' => 'campanhas_patrocinadas', 'sprint' => true]);
        $this->rounds($story, [['files' => ['story-orcamento'], 'status' => 'pending', 'sent' => -1, 'caption' => 'Story para anúncio: “Orçamento em 48 horas”. Vai rodar no conjunto de interesses.']]);

        $mez = $this->task(['project' => $obra, 'title' => 'Anúncio “Mezanino em 15 dias”', 'type' => 'criacao', 'status' => 'aprovacao', 'who' => 'lucas',
            'due' => $this->d(2), 'created' => $this->d(-5), 'situation' => 'Em Aprovação Cliente', 'destination' => 'campanhas_patrocinadas', 'sprint' => true]);
        $this->rounds($mez, [['files' => ['ad-mezanino'], 'status' => 'pending', 'sent' => 0, 'caption' => 'Anúncio para a campanha de mezanino: “Dobre a área sem mudar de endereço”.']]);

        $this->task(['project' => $obra, 'title' => 'Configurar campanhas no Meta e no Google', 'type' => 'trafego', 'status' => 'concluido', 'who' => 'thiago',
            'due' => $this->d(-41), 'created' => $this->d(-46), 'done_at' => $this->d(-40)]);
        $this->task(['project' => $obra, 'title' => 'Página de orçamento com formulário qualificado', 'type' => 'web', 'status' => 'concluido', 'who' => 'diego',
            'due' => $this->d(-42), 'created' => $this->d(-49), 'done_at' => $this->d(-42)]);
        $otm = $this->task(['project' => $obra, 'title' => 'Otimização semanal das campanhas', 'type' => 'trafego', 'status' => 'em_producao', 'who' => 'thiago',
            'due' => $this->d(0), 'created' => $this->d(-7), 'sprint' => true, 'situation' => 'Em Setup / Dev']);
        $this->comment($otm, 'thiago', 'Esta semana: pausar o anúncio com custo alto no público de interesses e subir o orçamento do público semelhante.', -1, true);
        $this->task(['project' => $obra, 'title' => 'Relatório mensal de resultados', 'type' => 'trafego', 'status' => 'backlog', 'who' => 'thiago',
            'due' => $this->today->copy()->endOfMonth()->addDays(2), 'created' => $this->d(-3)]);

        // ── Bastidores
        $pl = $this->task(['project' => $bast, 'title' => 'Post “Corte a plasma CNC”', 'type' => 'social', 'status' => 'concluido', 'who' => 'bruna',
            'due' => $this->d(-21), 'created' => $this->d(-27), 'done_at' => $this->d(-19), 'publish' => $this->d(-19), 'situation' => 'Publicado / No Ar']);
        $this->rounds($pl, [['files' => ['bastidores-plasma'], 'status' => 'approved', 'sent' => -23, 'resolved' => -23,
            'caption' => "Precisão de 0,5 mm. 🔥\n\nÉ assim que cada peça da sua estrutura começa: no corte a plasma CNC da nossa fábrica em Chapecó.\n\n#bastidores #estruturametalica #industria"]]);

        $ch = $this->task(['project' => $bast, 'title' => 'Post “Obra entregue — CD Chapecó”', 'type' => 'social', 'status' => 'aprovacao', 'who' => 'bruna',
            'due' => $this->d(1), 'created' => $this->d(-4), 'situation' => 'Em Aprovação Cliente', 'publish' => $this->d(3), 'sprint' => true]);
        $this->rounds($ch, [['files' => ['obra-entregue-chapeco'], 'status' => 'pending', 'sent' => -1,
            'caption' => "Mais uma entregue no prazo. ✅\n\nCentro de distribuição em Chapecó/SC: 4.200 m² cobertos em 84 dias de obra — e zero acidentes.\n\n#obraentregue #galpao #logistica"]]);

        $mx = $this->task(['project' => $bast, 'title' => 'Post “Metálica x Concreto”', 'type' => 'social', 'status' => 'ajuste_alteracao', 'who' => 'bruna',
            'due' => $this->d(2), 'created' => $this->d(-8), 'situation' => 'Alteração Solicitada', 'sprint' => true]);
        $this->rounds($mx, [['files' => ['metalica-x-concreto'], 'status' => 'changes_requested', 'sent' => -3, 'resolved' => -2,
            'caption' => "Metálica ou concreto? A resposta depende da obra — mas o prazo costuma decidir. ⏱️\n\n#estruturametalica #construcao",
            'feedback' => 'Prefiro não falar mal do concreto, temos parceiros que trabalham com pré-moldado. Pode trocar os pontos da direita por “quando o concreto faz sentido”?']]);
        $this->comment($mx, 'bruna', 'Entendido, Rodrigo! Vamos ajustar o lado direito para um tom neutro e reenviar até amanhã.', -2, true);

        $this->task(['project' => $bast, 'title' => 'Reels “Da chapa ao galpão”', 'type' => 'video', 'status' => 'em_producao', 'who' => 'rafaela',
            'due' => $this->d(2), 'created' => $this->d(-9), 'situation' => 'Em design', 'sprint' => true]);
        $this->task(['project' => $bast, 'title' => 'Post “Dia do Engenheiro”', 'type' => 'social', 'status' => 'revisao_interna', 'who' => 'bruna',
            'due' => $this->d(5), 'created' => $this->d(-3), 'situation' => 'Revisão Interna', 'sprint' => true]);
        $this->task(['project' => $bast, 'title' => 'Captação de bastidores na fábrica', 'type' => 'video', 'status' => 'backlog', 'who' => 'rafaela',
            'due' => $this->d(8), 'created' => $this->d(-10), 'sprint' => true]);
        $this->task(['project' => $bast, 'title' => 'Post de boas festas', 'type' => 'social', 'status' => 'backlog', 'who' => 'bruna',
            'due' => $this->d(27), 'created' => $this->d(-2)]);
        $late = $this->task(['project' => $bast, 'title' => 'Legenda e agendamento: obra de Concórdia', 'type' => 'social', 'status' => 'em_producao', 'who' => 'bruna',
            'due' => $this->d(-2), 'created' => $this->d(-9), 'situation' => 'Pendente de Informações', 'sprint' => true]);
        $this->comment($late, 'bruna', 'Juliana, conseguimos as fotos finais da obra de Concórdia? Assim que chegarem, agendamos a publicação.', -2, true);
        $this->comment($late, 'juliana', 'Oi, Bruna! O engenheiro manda as fotos até sexta.', -1, true);

        // ── Catálogo
        $diag = $this->task(['project' => $cat, 'title' => 'Diagramação do catálogo técnico', 'type' => 'criacao', 'status' => 'aprovacao', 'who' => 'lucas',
            'due' => $this->d(0), 'created' => $this->d(-12), 'situation' => 'Em Aprovação Cliente', 'sprint' => true]);
        $this->rounds($diag, [['files' => ['catalogo-tecnico-aurora-2027'], 'status' => 'pending', 'sent' => -2,
            'caption' => 'Primeira versão completa do catálogo (3 páginas). Confira principalmente as tabelas de vão e de carga.']]);
        $this->task(['project' => $cat, 'title' => 'Tabelas de carga e vãos (conteúdo técnico)', 'type' => 'estrategia', 'status' => 'concluido', 'who' => 'patricia',
            'due' => $this->d(-15), 'created' => $this->d(-25), 'done_at' => $this->d(-14)]);
        $ban = $this->task(['project' => $cat, 'title' => 'Banner do catálogo para o site', 'type' => 'criacao', 'status' => 'despacho_agendamento', 'who' => 'lucas',
            'due' => $this->d(-1), 'created' => $this->d(-8), 'situation' => 'Aprovado', 'sprint' => true]);
        $this->rounds($ban, [['files' => ['banner-catalogo'], 'status' => 'approved', 'sent' => -4, 'resolved' => -3, 'caption' => 'Banner da home para divulgar o catálogo.']]);
        $this->task(['project' => $cat, 'title' => 'Página de download com cadastro', 'type' => 'web', 'status' => 'em_producao', 'who' => 'diego',
            'due' => $this->d(7), 'created' => $this->d(-6), 'situation' => 'Em Setup / Dev', 'sprint' => true]);

        // ── Vídeo
        $this->task(['project' => $video, 'title' => 'Roteiro do vídeo institucional', 'type' => 'estrategia', 'status' => 'concluido', 'who' => 'patricia',
            'due' => $q4s->copy()->addDays(16), 'created' => $q4s->copy()->addDays(5), 'done_at' => $q4s->copy()->addDays(16)]);
        $this->task(['project' => $video, 'title' => 'Gravação na fábrica', 'type' => 'video', 'status' => 'concluido', 'who' => 'rafaela',
            'due' => $q4s->copy()->addDays(22), 'created' => $q4s->copy()->addDays(10), 'done_at' => $q4s->copy()->addDays(22)]);
        $ed = $this->task(['project' => $video, 'title' => 'Edição — versão de 60 segundos', 'type' => 'video', 'status' => 'revisao_interna', 'who' => 'rafaela',
            'due' => $this->d(1), 'created' => $this->d(-14), 'situation' => 'Revisão Interna', 'sprint' => true]);
        $this->comment($ed, 'rafaela', 'Primeiro corte pronto, passando pela revisão do time antes de enviar pra vocês.', -1, true);
        $this->task(['project' => $video, 'title' => 'Cortes de 15 segundos para anúncios', 'type' => 'video', 'status' => 'backlog', 'who' => 'rafaela',
            'due' => $this->d(10), 'created' => $this->d(-14)]);

        // ── Newsletter
        $tpl = $this->task(['project' => $news, 'title' => 'Modelo de e-mail da newsletter', 'type' => 'email', 'status' => 'aprovacao', 'who' => 'lucas',
            'due' => $this->d(3), 'created' => $this->d(-5), 'situation' => 'Em Aprovação Cliente', 'sprint' => true]);
        $this->rounds($tpl, [['files' => ['email-header'], 'status' => 'pending', 'sent' => -1, 'caption' => 'Cabeçalho e estilo da Newsletter da Engenharia. A primeira edição fala de cobertura termoacústica.']]);
        $this->task(['project' => $news, 'title' => 'Newsletter nº 7 — Cobertura termoacústica', 'type' => 'email', 'status' => 'backlog', 'who' => 'patricia',
            'due' => $this->d(15), 'created' => $this->d(-5)]);

        // ── Avulsas (sem projeto)
        $eng = $this->task(['title' => 'Post “Dia do Engenheiro” (versão para o LinkedIn)', 'type' => 'social', 'status' => 'aprovacao', 'who' => 'bruna',
            'due' => $this->d(4), 'created' => $this->d(-2), 'situation' => 'Em Aprovação Cliente', 'origin' => 'roadmap', 'sprint' => true, 'macro' => $q4]);
        $this->rounds($eng, [['files' => ['dia-engenheiro'], 'status' => 'pending', 'sent' => 0, 'caption' => "Hoje é dia de quem transforma cálculo em estrutura de pé. Obrigado, engenheiros! 👷‍♀️👷\n\n#diadoengenheiro"]]);
        $this->task(['title' => 'Atualizar fotos do Google Meu Negócio', 'type' => 'seo', 'status' => 'concluido', 'who' => 'diego',
            'due' => $this->d(-12), 'created' => $this->d(-18), 'done_at' => $this->d(-12), 'origin' => 'roadmap', 'macro' => $q4]);

        $this->mk(ProjectComment::class, [
            'organization_id' => $this->org->id, 'project_id' => $obra->id, 'user_id' => $this->u['thiago']->id,
            'body' => '<p>Resumo da semana: 31 contatos e 11 com perfil de obra. O público semelhante segue trazendo os melhores leads.</p>',
            'visible_to_client' => true, 'created_at' => $this->days(-2, '18:05'), 'updated_at' => $this->days(-2, '18:05'),
        ]);
    }

    private function project(MacroPlan $plan, int $pos, string $type, string $title, string $status, array $disc, string $objective, Carbon $start, Carbon $end, array $extra = []): Project
    {
        return $this->mk(Project::class, array_merge([
            'organization_id' => $this->org->id, 'macro_plan_id' => $plan->id, 'client_id' => $this->client->id,
            'position' => $pos, 'title' => $title, 'objective' => $objective, 'disciplines' => $disc, 'status' => $status,
            'type' => $type, 'start_date' => $start, 'end_date' => $end, 'brief_status' => 'basico',
            'launched_at' => $start->copy()->subDays(2), 'created_at' => $plan->created_at, 'updated_at' => $start,
        ], $extra));
    }

    private function phases(Project $project, array $phases): void
    {
        foreach ($phases as $i => [$name, $start, $end, $milestone]) {
            $this->mk(ProjectPhase::class, [
                'organization_id' => $this->org->id, 'project_id' => $project->id, 'position' => $i + 1,
                'name' => $name, 'is_milestone' => $milestone, 'start_date' => $start, 'end_date' => $end,
            ]);
        }
    }

    private function task(array $o): Task
    {
        $who = $this->u[$o['who']];
        $created = $o['created'] instanceof Carbon ? $o['created']->copy()->setTime(9, 30) : $o['created'];
        $status = $o['status'];
        $isTicket = $o['ticket'] ?? false;
        $sprint = null;
        if (!empty($o['sprint'])) {
            $sprint = $this->sprint->id;
        } elseif ($status === 'concluido' && isset($o['done_at']) && $o['done_at']->gte($this->prevSprint->starts_at)) {
            $sprint = $this->prevSprint->id;
        }

        $task = $this->mk(Task::class, [
            'organization_id' => $this->org->id,
            'client_id' => $this->client->id,
            'project_id' => isset($o['project']) ? $o['project']->id : null,
            'macro_plan_id' => isset($o['project']) ? $o['project']->macro_plan_id : (isset($o['macro']) ? $o['macro']->id : null),
            'title' => $o['title'],
            'description' => $o['description'] ?? null,
            'task_type' => $o['type'],
            'status' => $status,
            'situation' => $o['situation'] ?? null,
            'executor_id' => $who->id,
            'created_by' => isset($o['contact']) ? null : $this->u['mariana']->id,
            'contact_id' => isset($o['contact']) ? $this->c[$o['contact']]->id : null,
            'requester_name' => isset($o['contact']) ? $this->c[$o['contact']]->name : null,
            'requester_channel' => $isTicket && !isset($o['contact']) ? 'whatsapp' : null,
            'due_date' => $o['due'] ?? null,
            'approval_date' => in_array($o['type'], ['criacao', 'social', 'video', 'email'], true) ? ($o['due'] ?? null) : null,
            'publish_date' => $o['publish'] ?? null,
            'origin' => $o['origin'] ?? ($isTicket ? 'ticket' : 'projeto'),
            'is_ticket' => $isTicket,
            'priority' => $o['priority'] ?? 'normal',
            'destination' => $o['destination'] ?? (in_array($o['type'], ['social'], true) ? 'publicacao_organica' : null),
            'approval_method' => 'aprovaaí',
            'sprint_id' => $sprint,
            'queued_at' => $created,
            'created_at' => $created,
            'updated_at' => isset($o['done_at']) ? $o['done_at']->copy()->setTime(17, 0) : $this->days(-1, '17:00'),
        ]);

        $this->mk(TaskExecutor::class, ['task_id' => $task->id, 'user_id' => $who->id, 'role' => 'executor']);
        $this->mk(TaskExecutor::class, ['task_id' => $task->id, 'user_id' => $this->u['mariana']->id, 'role' => 'responsavel']);

        $this->transition($task, null, 'backlog', $created);
        if ($status !== 'backlog') {
            $this->transition($task, 'backlog', 'em_producao', $created->copy()->addDay()->setTime(10, 0));
        }
        if ($status === 'concluido' && isset($o['done_at'])) {
            $this->transition($task, 'em_producao', 'concluido', $o['done_at']->copy()->setTime(17, 0));
        }

        $this->counts[$isTicket ? 'Chamados' : 'Tarefas'] = ($this->counts[$isTicket ? 'Chamados' : 'Tarefas'] ?? 0) + 1;

        return $task;
    }

    private function transition(Task $task, ?string $from, string $to, Carbon $at): void
    {
        $this->mk(TaskStatusTransition::class, [
            'task_id' => $task->id, 'from_status' => $from, 'to_status' => $to,
            'changed_by' => $this->u['mariana']->id, 'changed_at' => $at, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function comment(Task $task, string $who, string $text, int $days, bool $visible, string $time = '14:20'): void
    {
        $isContact = isset($this->c[$who]);
        $this->mk(TaskComment::class, [
            'task_id' => $task->id,
            'user_id' => $isContact ? null : $this->u[$who]->id,
            'contact_id' => $isContact ? $this->c[$who]->id : null,
            'body' => '<p>' . e($text) . '</p>',
            'visible_to_client' => $visible || $isContact,
            'created_at' => $this->days($days, $time), 'updated_at' => $this->days($days, $time),
        ]);
    }

    /** Rodadas de aprovação: entregáveis, links dos aprovadores e o retorno do cliente. */
    private function rounds(Task $task, array $rounds): void
    {
        foreach ($rounds as $i => $r) {
            $n = $i + 1;
            $sent = $this->days($r['sent'], '11:15');
            $resolved = isset($r['resolved']) ? $this->days($r['resolved'], '16:40') : null;
            $round = $this->mk(TaskApprovalRound::class, [
                'task_id' => $task->id, 'round_number' => $n, 'submitted_by' => $this->u['mariana']->id,
                'submitted_at' => $sent->copy()->subMinutes(20), 'sent_at' => $sent, 'status' => $r['status'],
                'resolved_at' => $r['status'] === 'pending' ? null : $resolved, 'caption' => $r['caption'] ?? null,
                'type' => 'aprovacao', 'handled_at' => $r['status'] === 'changes_requested' ? $resolved?->copy()->addHours(2) : null,
                'created_at' => $sent, 'updated_at' => $resolved ?? $sent,
            ]);

            $atts = [];
            foreach ($r['files'] as $file) {
                $f = $this->files[$file];
                $atts[] = $this->mk(TaskAttachment::class, [
                    'task_id' => $task->id, 'filename' => $f['name'], 'disk_path' => $f['path'], 'disk' => 'r2',
                    'mime_type' => $f['mime'], 'size' => $f['size'], 'uploaded_by' => $task->executor_id,
                    'is_deliverable' => true, 'round_number' => $n, 'kind' => 'entregavel',
                    'created_at' => $sent->copy()->subMinutes(30), 'updated_at' => $sent->copy()->subMinutes(30),
                ]);
            }

            foreach (['rodrigo', 'juliana'] as $who) {
                $tokenStatus = match ($r['status']) {
                    'approved' => 'approved',
                    'pending' => 'pending',
                    default => $who === 'rodrigo' ? 'changes_requested' : 'approved',
                };
                $token = $this->mk(TaskApprovalToken::class, [
                    'round_id' => $round->id, 'contact_id' => $this->c[$who]->id, 'token' => Str::random(48),
                    'status' => $tokenStatus,
                    'overall_comment' => $tokenStatus === 'changes_requested' ? ($r['feedback'] ?? null) : null,
                    'notified_at' => $sent, 'first_opened_at' => $tokenStatus === 'pending' ? null : $sent->copy()->addHours(2),
                    'reviewed_at' => $tokenStatus === 'pending' ? null : ($resolved ?? $sent)->copy()->subMinutes($who === 'rodrigo' ? 0 : 50),
                    'expires_at' => $this->today->copy()->addDays(30), 'channels' => ['whatsapp'], 'will_notify' => true,
                    'created_at' => $sent, 'updated_at' => $resolved ?? $sent,
                ]);
                if ($tokenStatus === 'changes_requested') {
                    foreach ($atts as $att) {
                        $this->mk(DeliverableFeedback::class, ['token_id' => $token->id, 'attachment_id' => $att->id, 'status' => 'changes_requested', 'comment' => $r['feedback'] ?? null]);
                    }
                }
            }

            if ($r['status'] === 'changes_requested' && !empty($r['feedback'])) {
                $this->comment($task, 'rodrigo', $r['feedback'], $r['resolved'] ?? $r['sent'], true, '16:40');
            }
        }
        $this->counts['Peças em aprovação/aprovadas'] = ($this->counts['Peças em aprovação/aprovadas'] ?? 0) + 1;
    }

    // ─────────────────────────────────────────────────────────────── chamados

    private function tickets(): void
    {
        $k1 = $this->task(['ticket' => true, 'contact' => 'juliana', 'title' => 'Trocar o telefone do rodapé do site', 'type' => 'web', 'status' => 'concluido', 'who' => 'diego',
            'due' => $this->d(-9), 'created' => $this->d(-11), 'done_at' => $this->d(-10),
            'description' => 'O telefone do rodapé ainda é o antigo. O novo é (49) 3000-0000.']);
        $this->comment($k1, 'diego', 'Feito, Juliana! Já está no ar em todas as páginas.', -10, true, '15:05');
        $this->comment($k1, 'juliana', 'Perfeito, obrigada!', -10, true, '15:32');

        $k2 = $this->task(['ticket' => true, 'contact' => 'rodrigo', 'title' => 'Arte para a placa da obra em Concórdia', 'type' => 'criacao', 'status' => 'em_producao', 'who' => 'lucas',
            'due' => $this->d(3), 'created' => $this->d(-2), 'priority' => 'medio', 'situation' => 'Em design', 'sprint' => true,
            'description' => 'Placa de 3 x 2 metros para a frente da obra, com o logo, “Obra Aurora” e o telefone do comercial.']);
        $this->comment($k2, 'lucas', 'Recebido, Rodrigo. Mando a primeira versão até quinta.', -2, true, '10:12');

        $k3 = $this->task(['ticket' => true, 'contact' => 'juliana', 'title' => 'Incluir vaga de soldador no site', 'type' => 'web', 'status' => 'backlog', 'who' => 'diego',
            'due' => $this->d(6), 'created' => $this->d(-1),
            'description' => 'Precisamos de uma página simples de “Trabalhe conosco” com a vaga de soldador e um formulário para currículo.']);

        $k4 = $this->task(['ticket' => true, 'contact' => 'carlos', 'title' => 'Dúvida sobre a fatura do Google do mês passado', 'type' => 'administrativo', 'status' => 'concluido', 'who' => 'thiago',
            'due' => $this->d(-16), 'created' => $this->d(-18), 'done_at' => $this->d(-17),
            'description' => 'O valor da nota do Google veio diferente do boleto. Podem explicar?']);
        $this->comment($k4, 'thiago', 'Oi, Carlos! O boleto é o crédito que entra na conta; a nota é o que foi gasto de verdade no mês. A diferença fica de saldo para o mês seguinte — dá pra ver tudo em Prestação de contas, no Portal.', -17, true, '11:00');

        $k5 = $this->task(['ticket' => true, 'contact' => 'rodrigo', 'title' => 'Convite digital para visita à fábrica', 'type' => 'criacao', 'status' => 'backlog', 'who' => 'lucas',
            'due' => null, 'created' => $this->d(0), 'priority' => 'normal',
            'description' => 'Queremos convidar 10 clientes para conhecer a fábrica em dezembro. Pode ser uma arte para WhatsApp.']);
    }

    // ─────────────────────────────────────────────────────────────── reuniões

    private function meetings(): void
    {
        [$q3s, , $q4s] = $this->cycles();
        $q4 = MacroPlan::withoutGlobalScopes()->where('client_id', $this->client->id)->where('status', 'em_execucao')->first();

        $list = [
            ['Kick-off estratégico', 'kickoff_estrategico', 'presencial_cliente', 'realizada', $q3s->copy()->addDays(2)->setTime(9, 0), 120, null, 'kickoff'],
            ['Planejamento do trimestre', 'macroplanejamento', 'online', 'realizada', $q4s->copy()->subDays(4)->setTime(14, 0), 90, $q4, 'macro'],
            ['Alinhamento da campanha Obra Pronta', 'alinhamento_projeto', 'online', 'realizada', $this->days(-38, '10:00'), 45, $q4, 'obra'],
            ['Reunião mensal de resultados', 'outros', 'online', 'realizada', $this->days(-15, '10:00'), 60, $q4, 'mensal'],
            ['Reunião mensal de resultados', 'outros', 'online', 'agendada', $this->days(6, '10:00'), 60, $q4, null],
            ['Visita à fábrica — captação de bastidores', 'evento_captacao', 'presencial_cliente', 'agendada', $this->days(8, '08:30'), 180, $q4, null],
            ['Planejamento do próximo trimestre', 'macroplanejamento', 'online', 'para_agendar', $this->days(38, '14:00'), 90, null, null],
        ];

        foreach ($list as [$title, $type, $mod, $status, $at, $dur, $plan, $ata]) {
            $meeting = $this->mk(Meeting::class, [
                'organization_id' => $this->org->id, 'client_id' => $this->client->id, 'title' => $title, 'type' => $type,
                'modality' => $mod, 'status' => $status, 'organized_by' => $this->u['mariana']->id, 'created_by' => $this->u['mariana']->id,
                'scheduled_at' => $at, 'duration_minutes' => $dur,
                'location' => str_starts_with($mod, 'presencial') ? 'Fábrica Aurora — Rodovia SC-480, km 12, Chapecó/SC' : null,
                'online_link' => $mod === 'online' ? 'https://meet.google.com/' : null,
                'agenda' => $this->agenda($type, $status),
                'ata' => $ata ? $this->ata($ata) : null,
                'ata_recorded_at' => $ata ? $at->copy()->addHours(3) : null,
                'next_steps' => $ata ? 'Ver ATA.' : null,
                'macro_plan_id' => $plan?->id,
                'created_at' => $at->copy()->subDays(7), 'updated_at' => $ata ? $at->copy()->addHours(3) : $at->copy()->subDays(7),
            ]);
            foreach (['mariana', $type === 'macroplanejamento' ? 'patricia' : 'thiago'] as $who) {
                $this->mk(MeetingParticipant::class, ['meeting_id' => $meeting->id, 'user_id' => $this->u[$who]->id]);
            }
        }
        $this->counts['Reuniões'] = count($list);
    }

    private function agenda(string $type, string $status): string
    {
        return match (true) {
            $type === 'macroplanejamento' && $status === 'para_agendar' => '<ul><li>Resultado do trimestre</li><li>Prioridades do próximo ciclo</li><li>Verba de mídia</li></ul>',
            $type === 'macroplanejamento' => '<ul><li>Resultado do ciclo anterior</li><li>Foco e metas do trimestre</li><li>Projetos e campanhas</li><li>Verba de mídia</li></ul>',
            $type === 'evento_captacao' => '<ul><li>Corte a plasma e solda</li><li>Pintura</li><li>Depoimento do Rodrigo (2 minutos)</li></ul>',
            $type === 'kickoff_estrategico' => '<ul><li>História da Aurora</li><li>Cliente ideal</li><li>Concorrentes</li><li>Metas do primeiro ciclo</li></ul>',
            default => '<ul><li>Resultados das campanhas</li><li>Central de Leads: o que virou orçamento</li><li>Peças em aprovação</li><li>Próximos passos</li></ul>',
        };
    }

    private function ata(string $kind): string
    {
        return match ($kind) {
            'kickoff' => '<h3>Resumo</h3><p>Conhecemos a história da Aurora, a fábrica e o processo comercial. O cliente ideal é indústria, agroindústria e logística com obra prevista em até 12 meses, no Oeste de SC, RS e PR.</p><h3>Decisões</h3><ul><li>Primeiro ciclo foca em site novo e medição de resultados.</li><li>A promessa central da marca será o prazo em contrato.</li></ul><h3>Próximos passos</h3><ul><li>Nonna: proposta de estrutura do site.</li><li>Aurora: enviar fotos e dados das últimas 10 obras.</li></ul>',
            'macro' => '<h3>Resumo</h3><p>Apresentamos o resultado do ciclo anterior (214 contatos, 61 com obra prevista) e o planejamento do trimestre.</p><h3>Decisões</h3><ul><li>Campanha principal: Obra Pronta, com verba de R$ 9.000/mês (R$ 5.000 Meta e R$ 4.000 Google).</li><li>Entram também o catálogo técnico, o vídeo institucional e a newsletter.</li><li>O Carlos fica responsável por marcar na Central de Leads os contatos que viraram orçamento.</li></ul><h3>Próximos passos</h3><ul><li>Nonna: peças da campanha em até 5 dias.</li><li>Aurora: agendar a gravação na fábrica.</li></ul>',
            'obra' => '<h3>Resumo</h3><p>Revisamos as peças da campanha Obra Pronta e o formulário da página de orçamento.</p><h3>Decisões</h3><ul><li>Formulário pergunta área, cidade e prazo da obra.</li><li>Key visual aprovado na versão com fundo azul.</li></ul><h3>Próximos passos</h3><ul><li>Campanha no ar até sexta-feira.</li></ul>',
            default => '<h3>Resumo</h3><p>No mês, a campanha Obra Pronta trouxe 64 contatos, 22 com perfil de obra, com custo por lead bom de R$ 158. Duas obras estão em negociação.</p><h3>Decisões</h3><ul><li>Mais verba para o público semelhante, que traz os melhores leads.</li><li>Vídeo institucional entra nos anúncios assim que for aprovado.</li></ul><h3>Próximos passos</h3><ul><li>Nonna: anúncio de mezanino e story de orçamento.</li><li>Aurora: retorno sobre o catálogo técnico.</li></ul>',
        };
    }

    // ─────────────────────────────────────────────────────────────── leads

    private function leads(): void
    {
        $firsts = ['João', 'Marcos', 'Ana', 'Paulo', 'Fernanda', 'Ricardo', 'Luciana', 'André', 'Cláudia', 'Eduardo', 'Patrícia', 'Gustavo', 'Simone', 'Rafael', 'Márcia', 'Felipe', 'Daniela', 'Sérgio', 'Juliano', 'Tatiane', 'Vinícius', 'Elisa', 'Roberto', 'Camila', 'Leandro', 'Aline', 'Fábio', 'Priscila', 'Henrique', 'Larissa'];
        $lasts = ['Zanella', 'Baretta', 'Schneider', 'Bortolini', 'Dalla Costa', 'Webber', 'Fontana', 'Pasqualotto', 'Kunz', 'Mattei', 'Lorenzi', 'Hoffmann', 'Bianchi', 'Tomazoni', 'Rossetto', 'Pereira', 'Grando', 'Mezzomo', 'Frizon', 'Antunes'];
        $kinds = ['Transportadora', 'Agroindustrial', 'Cerealista', 'Laticínios', 'Distribuidora', 'Cooperativa', 'Madeireira', 'Logística', 'Indústria de Embalagens', 'Granja', 'Comercial', 'Metalúrgica'];
        $names = ['Vale Verde', 'Serra Alta', 'Rota Sul', 'Três Pinheiros', 'Bom Retiro', 'Campo Belo', 'Nova Aliança', 'Santa Clara', 'Rio Uruguai', 'Planalto', 'do Oeste', 'Pinhal', 'Água Doce', 'Boa Vista', 'Horizonte'];
        $cities = [['Chapecó', 'SC'], ['Concórdia', 'SC'], ['Xanxerê', 'SC'], ['Joaçaba', 'SC'], ['São Miguel do Oeste', 'SC'], ['Videira', 'SC'], ['Caçador', 'SC'], ['Erechim', 'RS'], ['Passo Fundo', 'RS'], ['Frederico Westphalen', 'RS'], ['Pato Branco', 'PR'], ['Francisco Beltrão', 'PR'], ['Cascavel', 'PR']];
        $works = ['Galpão industrial', 'Galpão de armazenagem', 'Cobertura', 'Mezanino', 'Ampliação de galpão', 'Centro de distribuição'];

        $this->sources['site'] = $this->mk(ClientLeadSource::class, ['client_id' => $this->client->id, 'lead_channel_id' => $this->channels['site']->id, 'external_id' => 'auroraestruturas.com.br', 'label' => 'Site — página de orçamento', 'is_active' => true]);
        $this->sources['facebook_lead_ad'] = $this->mk(ClientLeadSource::class, ['client_id' => $this->client->id, 'lead_channel_id' => $this->channels['facebook_lead_ad']->id, 'external_id' => '000000000000001', 'label' => 'Formulário Obra Pronta (Instagram/Facebook)', 'is_active' => true]);
        $this->sources['whatsapp'] = $this->mk(ClientLeadSource::class, ['client_id' => $this->client->id, 'lead_channel_id' => $this->channels['whatsapp']->id, 'external_id' => '5549900000003', 'label' => 'WhatsApp Comercial', 'is_active' => true]);

        $total = 0;
        $won = 0;
        $this->waLeads = [];
        for ($ago = 112; $ago >= 0; $ago--) {
            $day = $this->today->copy()->subDays($ago);
            $perDay = [0 => 1, 6 => 1][$day->dayOfWeek] ?? 2;
            $perDay += mt_rand(0, 2) === 0 ? 1 : 0;
            if ($ago === 0) {
                $perDay = 3;
            }
            for ($i = 0; $i < $perDay; $i++) {
                $total++;
                $first = $firsts[mt_rand(0, count($firsts) - 1)];
                $last = $lasts[mt_rand(0, count($lasts) - 1)];
                $company = $kinds[mt_rand(0, count($kinds) - 1)] . ' ' . $names[mt_rand(0, count($names) - 1)];
                [$city, $uf] = $cities[mt_rand(0, count($cities) - 1)];
                $work = $works[mt_rand(0, count($works) - 1)];
                $roll = mt_rand(1, 100);
                $quality = $roll <= 36 ? 'bom' : ($roll <= 76 ? 'fraco' : 'sem_perfil');
                $area = match ($quality) { 'bom' => mt_rand(6, 40) * 100, 'fraco' => mt_rand(2, 8) * 100, default => mt_rand(1, 3) * 50 };
                $when = match ($quality) { 'bom' => ['Nos próximos 3 meses', 'Em até 6 meses', 'Ainda este ano'][mt_rand(0, 2)], 'fraco' => ['Ainda não sei', 'Ano que vem', 'Só pesquisando preço'][mt_rand(0, 2)], default => '—' };
                if ($quality === 'sem_perfil') {
                    $work = ['Garagem residencial', 'Quero trabalhar na Aurora', 'Vendo chapas de aço', 'Cobertura de churrasqueira'][mt_rand(0, 3)];
                }

                $kindRoll = mt_rand(1, 100);
                $channel = $kindRoll <= 42 ? 'site' : ($kindRoll <= 74 ? 'facebook_lead_ad' : 'whatsapp');
                $recentCycle = $ago <= 47;
                $campKey = match ($channel) {
                    'site' => mt_rand(0, 2) ? ($recentCycle ? ['c1', 'g1', 'g2', 'c2'][mt_rand(0, 3)] : ['c4', 'g1', 'c2'][mt_rand(0, 2)]) : null,
                    'facebook_lead_ad' => $recentCycle ? 'c1' : 'c4',
                    default => mt_rand(0, 1) ? ($recentCycle ? 'c1' : 'c4') : null,
                };
                if ($campKey === 'g2' && $ago > 78) {
                    $campKey = 'g1';
                }
                $camp = $campKey ? $this->campaigns[$campKey]['model'] : null;
                $ad = $campKey ? $this->campaigns[$campKey]['ads'][mt_rand(0, 3)] : null;

                // Estágio: quanto mais antigo, mais fechado.
                $stage = $this->stageFor($quality, $ago);
                if ($stage === 'ganho') {
                    $won++;
                }
                $received = $day->copy()->setTime(mt_rand(7, 20), mt_rand(0, 59));
                if ($ago === 0) {
                    $received = now()->subMinutes(mt_rand(20, 300));
                }
                $phoneTail = str_pad((string) (1000 + $total), 4, '0', STR_PAD_LEFT);
                $phone = '(49) 90000-' . $phoneTail;
                $email = Str::slug($first . '.' . $last, '.') . '@exemplo.com.br';

                $lead = $this->mk(ClientLead::class, [
                    'client_id' => $this->client->id, 'name' => "{$first} {$last}", 'email' => $channel === 'whatsapp' ? null : $email,
                    'phone' => $phone, 'phone_key' => \App\Support\PhoneKey::from($phone), 'city' => $city, 'state' => $uf,
                    'first_contacted_at' => $received, 'last_contacted_at' => $received, 'first_seen_channel_id' => $this->channels[$channel]->id,
                    'created_at' => $received, 'updated_at' => $received,
                ]);

                $answers = [
                    ['label' => 'Nome', 'value' => "{$first} {$last}"],
                    ['label' => 'Empresa', 'value' => $quality === 'sem_perfil' ? '' : $company],
                    ['label' => 'WhatsApp', 'value' => $phone],
                    ['label' => 'Cidade da obra', 'value' => "{$city}/{$uf}"],
                    ['label' => 'Tipo de obra', 'value' => $work],
                    ['label' => 'Área aproximada (m²)', 'value' => (string) $area],
                    ['label' => 'Quando precisa da obra pronta?', 'value' => $when],
                ];
                [$summary, $reason] = $this->aiText($quality, $first, $company, $work, $area, $city, $uf, $when);
                $deal = $stage === 'ganho' ? round($area * mt_rand(560, 820) / 1000) * 1000 : null;
                $isMeta = $camp && $camp->platform === 'meta';
                $isGoogle = $camp && $camp->platform === 'google';

                $opp = $this->mk(ClientLeadOpportunity::class, [
                    'client_lead_id' => $lead->id, 'client_lead_source_id' => $this->sources[$channel]->id,
                    'lead_channel_id' => $this->channels[$channel]->id, 'stage' => $stage,
                    'utm_source' => $channel === 'site' ? ($isMeta ? 'ig' : ($isGoogle ? 'google' : 'organico')) : null,
                    'utm_medium' => $channel === 'site' ? ($isMeta ? 'paid_social' : ($isGoogle ? 'cpc' : null)) : null,
                    'utm_campaign' => $channel === 'site' && $camp ? $camp->external_id : null,
                    'utm_content' => $channel === 'site' && $ad ? $ad->external_id : null,
                    'landing_page_url' => $channel === 'site' ? 'https://auroraestruturas.com.br/orcamento' : null,
                    'form_name' => match ($channel) { 'site' => 'Formulário de orçamento', 'facebook_lead_ad' => 'Formulário Obra Pronta', default => null },
                    'form_answers' => $channel === 'whatsapp' ? null : $answers,
                    'raw_payload' => ['demo' => true, 'fields' => collect($answers)->pluck('value', 'label')->all()],
                    'received_at' => $received,
                    'fbclid' => $isMeta && $channel === 'site' ? 'demo' . Str::random(20) : null,
                    'gclid' => $isGoogle ? 'demo' . Str::random(20) : null,
                    'ctwa_clid' => $channel === 'whatsapp' && $camp ? 'demo' . Str::random(24) : null,
                    'event_id' => (string) Str::uuid(),
                    'campaign_external_id' => $camp?->external_id, 'ad_external_id' => $ad?->external_id,
                    'ad_campaign_id' => $camp?->id, 'ad_ad_id' => $ad?->id,
                    'assigned_contact_id' => $ago >= 1 && $quality !== 'sem_perfil' ? $this->c['carlos']->id : null,
                    'assigned_at' => $ago >= 1 && $quality !== 'sem_perfil' ? $received->copy()->addMinutes(mt_rand(8, 90)) : null,
                    'deal_value' => $deal,
                    'won_at' => $stage === 'ganho' ? $received->copy()->addDays(mt_rand(15, 30)) : null,
                    'lost_at' => $stage === 'perdido' ? $received->copy()->addDays(mt_rand(2, 20)) : null,
                    'lost_reason' => $stage === 'perdido' ? $this->lostReason($quality) : null,
                    'ai_quality' => $quality,
                    'ai_outcome' => match ($stage) { 'ganho' => 'comprou', 'perdido' => 'desistiu', default => 'aberto' },
                    'ai_outcome_value' => $deal,
                    'ai_summary' => $summary, 'ai_reason' => $reason,
                    'ai_evaluated_at' => $received->copy()->addHours(2),
                    'quality' => in_array($stage, ['proposta_enviada', 'negociando', 'ganho'], true) ? $quality : null,
                    'quality_confirmed_at' => in_array($stage, ['proposta_enviada', 'negociando', 'ganho'], true) ? $received->copy()->addDays(2) : null,
                    'quality_confirmed_contact_id' => in_array($stage, ['proposta_enviada', 'negociando', 'ganho'], true) ? $this->c['carlos']->id : null,
                    'created_at' => $received, 'updated_at' => $received,
                ]);

                $this->leadNotes($opp, $stage, $received, $company, $area);
                if ($channel === 'whatsapp') {
                    $this->waLeads[] = [$lead, $opp, $camp, $ad];
                }
            }
        }
        $this->counts['Leads'] = $total;
        $this->counts['Leads ganhos (vendas)'] = $won;
    }

    private array $waLeads = [];

    private function stageFor(string $quality, int $ago): string
    {
        $r = mt_rand(1, 100);
        if ($quality === 'sem_perfil') {
            return $ago <= 1 ? 'novo' : 'perdido';
        }
        if ($quality === 'fraco') {
            return match (true) {
                $ago <= 1 => 'novo',
                $ago <= 7 => $r <= 70 ? 'em_contato' : 'novo',
                default => $r <= 65 ? 'perdido' : 'em_contato',
            };
        }

        return match (true) {
            $ago <= 1 => 'novo',
            $ago <= 6 => $r <= 60 ? 'em_contato' : 'qualificado',
            $ago <= 20 => $r <= 45 ? 'qualificado' : ($r <= 85 ? 'proposta_enviada' : 'negociando'),
            $ago <= 45 => $r <= 40 ? 'proposta_enviada' : ($r <= 70 ? 'negociando' : ($r <= 82 ? 'ganho' : 'perdido')),
            default => $r <= 22 ? 'ganho' : ($r <= 70 ? 'perdido' : 'negociando'),
        };
    }

    private function lostReason(string $quality): string
    {
        return match ($quality) {
            'sem_perfil' => 'Fora do perfil',
            'fraco' => ['Sem retorno', 'Só pesquisando preço', 'Adiou a obra'][mt_rand(0, 2)],
            default => ['Fechou com concorrente', 'Preço acima do orçamento', 'Adiou a obra'][mt_rand(0, 2)],
        };
    }

    private function aiText(string $q, string $first, string $company, string $work, int $area, string $city, string $uf, string $when): array
    {
        return match ($q) {
            'bom' => [
                "{$first}, da {$company}, quer um {$work} de cerca de {$area} m² em {$city}/{$uf}, com obra prevista: “{$when}”.",
                'Empresa identificada, obra com área relevante e prazo definido — perfil prioritário para visita técnica.',
            ],
            'fraco' => [
                "{$first} pediu informações sobre {$work} de aproximadamente {$area} m² em {$city}/{$uf}, sem prazo definido.",
                'Tem interesse real, mas falta prazo ou porte da obra para ser prioridade. Vale manter contato.',
            ],
            default => [
                "{$first} entrou em contato sobre “{$work}”.",
                'Não é obra industrial nem comercial: fora do perfil de cliente da Aurora.',
            ],
        };
    }

    private function leadNotes(ClientLeadOpportunity $opp, string $stage, Carbon $received, string $company, int $area): void
    {
        $steps = [
            'em_contato' => ['novo', 'em_contato', 'Primeiro contato feito pelo WhatsApp. Pediu fotos de obras parecidas.'],
            'qualificado' => ['em_contato', 'qualificado', "Confirmou a obra de {$area} m². Visita técnica agendada."],
            'proposta_enviada' => ['qualificado', 'proposta_enviada', 'Proposta enviada com prazo de 90 dias em contrato.'],
            'negociando' => ['proposta_enviada', 'negociando', 'Pediu para rever a condição de pagamento. Retorno na semana que vem.'],
            'ganho' => ['negociando', 'ganho', "Contrato assinado com a {$company}. 🎉"],
            'perdido' => ['em_contato', 'perdido', 'Cliente informou que não vai seguir agora.'],
        ];
        $order = ['em_contato', 'qualificado', 'proposta_enviada', 'negociando', 'ganho'];
        $path = $stage === 'perdido' ? ['em_contato', 'perdido'] : array_slice($order, 0, array_search($stage, $order, true) === false ? 0 : array_search($stage, $order, true) + 1);
        $at = $received->copy();
        foreach ($path as $i => $s) {
            $at = $at->copy()->addDays(max(1, $i * 3))->setTime(mt_rand(8, 18), mt_rand(0, 59));
            if ($at->gt(now())) {
                break;
            }
            [$from, $to, $text] = $steps[$s];
            $this->mk(ClientLeadOpportunityNote::class, [
                'client_lead_opportunity_id' => $opp->id, 'contact_id' => $this->c['carlos']->id,
                'body' => $text, 'from_stage' => $from, 'to_stage' => $to, 'created_at' => $at, 'updated_at' => $at,
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────── atendimento

    private function service(): void
    {
        $integration = $this->mk(ClientIntegration::class, [
            'client_id' => $this->client->id, 'provider' => 'uazapi', 'label' => 'WhatsApp Comercial Aurora',
            'external_id' => 'demo-' . Str::random(12), 'credentials' => null,
            'settings' => ['avg_ticket_value' => 420000, 'diagnostic_frequency_days' => 30, 'demo' => true],
            'status' => 'connected', 'last_synced_at' => $this->days(0, '07:00'), 'last_verified_at' => $this->days(0, '07:00'),
        ]);

        $convs = 0;
        foreach ($this->waLeads as [$lead, $opp, $camp, $ad]) {
            $conv = $this->mk(ServiceConversation::class, [
                'client_id' => $this->client->id, 'client_integration_id' => $integration->id,
                'external_thread_id' => 'demo-' . Str::random(16), 'contact_name' => $lead->name, 'contact_phone' => $lead->phone_key,
                'started_at' => $lead->first_contacted_at, 'last_message_at' => $lead->first_contacted_at->copy()->addHours(mt_rand(1, 60)),
                'message_count' => mt_rand(6, 48), 'is_group' => false, 'sentiment_score' => mt_rand(55, 92),
                'sentiment_trend' => ['subindo', 'estavel', 'caindo'][mt_rand(0, 2)],
                'ad_source_id' => $ad?->external_id, 'ad_title' => $ad?->name, 'ad_ctwa_clid' => $opp->ctwa_clid,
                'client_lead_id' => $lead->id,
            ]);
            $opp->forceFill(['service_conversation_id' => $conv->id])->save();
            $convs++;
        }
        // Conversas que não viraram lead (clientes da carteira, fornecedores).
        for ($i = 0; $i < 38; $i++) {
            $start = $this->today->copy()->subDays(mt_rand(0, 60))->setTime(mt_rand(8, 18), mt_rand(0, 59));
            $this->mk(ServiceConversation::class, [
                'client_id' => $this->client->id, 'client_integration_id' => $integration->id,
                'external_thread_id' => 'demo-' . Str::random(16), 'contact_name' => ['Cliente da carteira', 'Fornecedor', 'Obra em andamento'][mt_rand(0, 2)],
                'contact_phone' => '5549900009' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'started_at' => $start, 'last_message_at' => $start->copy()->addHours(mt_rand(1, 30)),
                'message_count' => mt_rand(3, 25), 'is_group' => false, 'sentiment_score' => mt_rand(60, 90), 'sentiment_trend' => 'estavel',
            ]);
            $convs++;
        }
        $this->counts['Conversas de WhatsApp'] = $convs;

        $prevStart = $this->today->copy()->subMonthsNoOverflow(2)->startOfMonth();
        $lastStart = $this->today->copy()->subMonthNoOverflow()->startOfMonth();
        $v1 = $this->diagnostic($integration, 1, $prevStart, $prevStart->copy()->endOfMonth(), 58, [
            'total' => 74, 'sold' => 2, 'neg' => 6, 'rate' => 2.7, 'resp' => 52, 'lost' => 840000,
            'achado' => 'Metade dos contatos espera mais de 1 hora pela primeira resposta — e quem espera mais de 1 hora quase nunca pede visita técnica.',
            'breakdown' => ['conversao' => 48, 'sentimento' => 72, 'velocidade' => 41, 'consistencia' => 66],
        ]);
        $v2 = $this->diagnostic($integration, 2, $lastStart, $lastStart->copy()->endOfMonth(), 71, [
            'total' => 86, 'sold' => 3, 'neg' => 8, 'rate' => 3.5, 'resp' => 24, 'lost' => 520000,
            'achado' => 'Com a resposta em menos de 30 minutos, mais contatos chegaram à visita técnica. O ponto fraco agora é o retorno depois da proposta.',
            'breakdown' => ['conversao' => 58, 'sentimento' => 78, 'velocidade' => 76, 'consistencia' => 70],
        ]);

        $recs1 = [
            ['quick_win', 'Primeira resposta em até 30 minutos', 'Responder rápido é o que mais aumenta a chance de visita técnica.', 'implementada'],
            ['quick_win', 'Mensagem pronta com fotos de obras parecidas', 'Quem recebe fotos de obras do mesmo tipo responde mais.', 'implementada'],
            ['estrutural', 'Retorno 3, 7 e 14 dias depois da proposta', 'Hoje a proposta é enviada e ninguém retoma o contato.', 'pendente'],
            ['estrutural', 'Número único para o comercial', 'Conversas espalhadas em celulares pessoais se perdem.', 'rejeitada'],
        ];
        $prev = [];
        foreach ($recs1 as $i => [$cat, $title, $desc, $st]) {
            $prev[$i] = $this->mk(ServiceDiagnosticRecommendation::class, ['diagnostic_id' => $v1->id, 'category' => $cat, 'title' => $title, 'description' => $desc, 'status' => $st, 'position' => $i + 1, 'resolved_at' => $st === 'pendente' ? null : $v1->published_at->copy()->addDays(10)]);
        }
        foreach ([
            ['quick_win', 'Manter a primeira resposta em até 30 minutos', 'Está funcionando: não deixar regredir.', 'pendente', 0],
            ['estrutural', 'Retorno 3, 7 e 14 dias depois da proposta (segue pendente)', 'Continua sendo a maior oportunidade: 8 propostas sem retorno no mês.', 'pendente', 2],
            ['quick_win', 'Perguntar o prazo da obra logo na primeira conversa', 'Ajuda a separar quem tem obra de quem está pesquisando preço.', 'pendente', null],
        ] as $i => [$cat, $title, $desc, $st, $p]) {
            $this->mk(ServiceDiagnosticRecommendation::class, ['diagnostic_id' => $v2->id, 'category' => $cat, 'title' => $title, 'description' => $desc, 'status' => $st, 'position' => $i + 1, 'previous_recommendation_id' => $p !== null ? $prev[$p]->id : null]);
        }
    }

    private function diagnostic(ClientIntegration $integration, int $version, Carbon $from, Carbon $to, int $score, array $d): ServiceDiagnostic
    {
        $diag = $this->mk(ServiceDiagnostic::class, [
            'client_id' => $this->client->id, 'client_integration_id' => $integration->id, 'version' => $version,
            'period_start' => $from, 'period_end' => $to, 'status' => 'published', 'generated_by' => 'manual',
            'created_by' => $this->u['mariana']->id, 'total_conversations' => $d['total'], 'sales_confirmed' => $d['sold'],
            'sales_in_negotiation' => $d['neg'], 'conversion_rate' => $d['rate'], 'avg_first_response_minutes' => $d['resp'],
            'funnel_summary' => [
                'como_entra' => 'A maioria chega pelos anúncios do Instagram e pelo botão de WhatsApp do site, já perguntando preço por metro quadrado.',
                'achado_central' => $d['achado'],
                'estagios' => [
                    ['estagio' => 'Entrada', 'observado' => 'Contatos pedem preço por m² logo na primeira mensagem.', 'onde_perde' => 'Quando a resposta demora, o contato some.'],
                    ['estagio' => 'Qualificação', 'observado' => 'O comercial pergunta área e cidade.', 'onde_perde' => 'Nem sempre pergunta o prazo da obra.'],
                    ['estagio' => 'Visita técnica', 'observado' => 'Quem faz a visita fecha muito mais.', 'onde_perde' => 'Visita marcada para longe demais da conversa.'],
                    ['estagio' => 'Proposta', 'observado' => 'Proposta em PDF com prazo em contrato.', 'onde_perde' => 'Sem retorno depois do envio.'],
                ],
            ],
            'gaps' => [
                ['titulo' => 'Sem retorno depois da proposta', 'descricao' => 'Propostas enviadas ficam sem novo contato.', 'estimated_leads_lost' => $version === 1 ? 6 : 4],
                ['titulo' => 'Prazo da obra não é perguntado', 'descricao' => 'Sem o prazo, o comercial não sabe quem priorizar.', 'estimated_leads_lost' => 3],
            ],
            'strengths' => [
                ['titulo' => 'Conhecimento técnico', 'descricao' => 'O comercial responde dúvidas técnicas com segurança, e o cliente percebe.'],
                ['titulo' => 'Fotos de obras', 'descricao' => 'Mostrar obras parecidas gera confiança e acelera a visita.'],
            ],
            'campaign_insights' => [
                ['titulo' => 'Obra entregue convence', 'descricao' => 'Contatos citam as obras que viram no anúncio — vale usar mais obras entregues nos criativos.'],
            ],
            'published_at' => $to->copy()->addDays(4)->setTime(10, 0),
            'service_score' => $score, 'service_score_breakdown' => $d['breakdown'], 'avg_sentiment_score' => $d['breakdown']['sentimento'],
            'estimated_lost_revenue' => $d['lost'],
            'created_at' => $to->copy()->addDays(3), 'updated_at' => $to->copy()->addDays(4),
        ]);

        foreach ([
            ['Persona A', 'Gerente de operações com urgência', 'Indústria ou logística crescendo, precisa de área nova em poucos meses.', 'Pergunta prazo antes de preço e quer visita rápida.', '“Preciso do galpão pronto antes da safra, vocês conseguem?”'],
            ['Persona B', 'Dono pesquisando preço', 'Empresa pequena, ainda sem projeto definido.', 'Pede preço por m² e some se não recebe resposta rápida.', '“Quanto fica o metro quadrado mais ou menos?”'],
            ['Persona C', 'Engenheiro especificador', 'Engenheiro ou arquiteto que vai indicar o fornecedor.', 'Quer catálogo técnico e tabela de carga.', '“Vocês têm a tabela de vãos do perfil?”'],
        ] as $i => [$tag, $name, $profile, $behavior, $evidence]) {
            $this->mk(ServiceDiagnosticPersona::class, ['diagnostic_id' => $diag->id, 'position' => $i + 1, 'tag' => $tag, 'name' => $name, 'profile' => $profile, 'behavior' => $behavior, 'evidence' => $evidence]);
        }

        return $diag;
    }

    // ─────────────────────────────────────────────────────────────── prestação de contas

    private function billing(): void
    {
        $monthly = $this->accounts['monthly'];
        $months = [];
        for ($i = 3; $i >= 0; $i--) {
            $months[] = $this->today->copy()->subMonthsNoOverflow($i)->startOfMonth();
        }
        $docs = 0;
        foreach ($months as $month) {
            $ids = [];
            $total = 0;
            foreach (['meta' => 5000, 'google' => 4000] as $key => $budget) {
                $account = $this->accounts[$key];
                $credit = $this->mk(ClientAdBillingDocument::class, [
                    'client_ad_account_id' => $account->id, 'type' => $key === 'meta' ? 'pix' : 'boleto', 'amount' => $budget,
                    'due_date' => $month->copy()->addDays(4), 'reference_month' => $month,
                    'pix_code' => $key === 'meta' ? '00020126DEMONSTRACAO0000PIX-FICTICIO-NAO-PAGAR' : null,
                    'filename' => $key === 'google' ? 'boleto-google-' . $month->format('m-Y') . '.pdf' : null,
                    'disk_path' => $key === 'google' ? $this->files['boleto-demo']['path'] : null,
                    'disk' => 'r2', 'mime_type' => $key === 'google' ? 'application/pdf' : null,
                    'size' => $key === 'google' ? $this->files['boleto-demo']['size'] : 0,
                    'notes' => 'Crédito de anúncios do mês.', 'created_by' => $this->u['thiago']->id,
                    'notified_at' => $month->copy()->addDays(1)->setTime(9, 0),
                    'created_at' => $month->copy()->addDay(), 'updated_at' => $month->copy()->addDay(),
                ]);
                $ids[] = $credit->id;
                $total += $budget;
                $docs++;

                $spent = round($monthly[$account->id][$month->format('Y-m')] ?? 0, 2);
                if ($month->lt($this->today->copy()->startOfMonth()) && $spent > 0) {
                    $nota = $this->mk(ClientAdBillingDocument::class, [
                        'client_ad_account_id' => $account->id, 'type' => 'nota', 'amount' => $spent,
                        'reference_month' => $month, 'filename' => 'nota-fiscal-' . $key . '-' . $month->format('m-Y') . '.pdf',
                        'disk_path' => $this->files['nota-fiscal-demo']['path'], 'disk' => 'r2', 'mime_type' => 'application/pdf',
                        'size' => $this->files['nota-fiscal-demo']['size'], 'created_by' => $this->u['thiago']->id,
                        'notified_at' => $month->copy()->endOfMonth()->addDays(5)->setTime(9, 0),
                        'created_at' => $month->copy()->endOfMonth()->addDays(5), 'updated_at' => $month->copy()->endOfMonth()->addDays(5),
                    ]);
                    $ids[] = $nota->id;
                    $docs++;
                }
            }
            $current = $month->isSameMonth($this->today);
            $this->mk(AdStatementDelivery::class, [
                'organization_id' => $this->org->id, 'client_id' => $this->client->id, 'month' => $month,
                'document_ids' => $ids, 'total' => $total, 'sent_by' => $this->u['thiago']->id,
                'sent_at' => $month->copy()->addDays(1)->setTime(9, 0),
                'viewed_at' => $current ? null : $month->copy()->addDays(2)->setTime(8, 40), 'view_count' => $current ? 0 : mt_rand(1, 4),
            ]);
        }
        $this->counts['Documentos de prestação de contas'] = $docs;
    }

    // ─────────────────────────────────────────────────────────────── apagar

    /**
     * Apaga a organização de demonstração e tudo dela, numa transação, com a lista
     * explícita de tabelas (filhos antes dos pais). Uma consulta por tabela — rápido.
     * Depois, qualquer outra tabela com organization_id (avisos, pendências criadas
     * enquanto alguém navegava na demo) é limpa pelo id da organização.
     */
    public function purge(): void
    {
        $org = $this->organization();
        if (!$org) {
            return;
        }
        if ($org->status !== 'demo') {
            throw new \RuntimeException('A organização ' . self::ORG_SLUG . ' não está marcada como demo — nada foi apagado.');
        }

        $db = DB::connection('pgsql');
        $db->transaction(function () use ($db, $org) {
            $id = $org->id;
            $c = "(select id from clients where organization_id = '{$id}')";
            $t = "(select id from tasks where client_id in {$c})";
            $r = "(select id from task_approval_rounds where task_id in {$t})";
            $p = "(select id from projects where client_id in {$c})";
            $m = "(select id from macro_plans where client_id in {$c})";
            $a = "(select id from client_ad_accounts where client_id in {$c})";
            $l = "(select id from client_leads where client_id in {$c})";
            $o = "(select id from client_lead_opportunities where client_lead_id in {$l})";
            $d = "(select id from service_diagnostics where client_id in {$c})";

            foreach ([
                "update projects set highlight_task_id = null, document_attachment_id = null where client_id in {$c}",
                "update macro_plans set document_attachment_id = null where client_id in {$c}",
                "delete from deliverable_feedbacks where token_id in (select id from task_approval_tokens where round_id in {$r})",
                "delete from task_approval_tokens where round_id in {$r}",
                "delete from task_approval_rounds where task_id in {$t}",
                "delete from task_attachments where task_id in {$t}",
                "delete from task_comments where task_id in {$t}",
                "delete from task_executors where task_id in {$t}",
                "delete from task_status_transitions where task_id in {$t}",
                "delete from task_activities where task_id in {$t}",
                "delete from tasks where client_id in {$c}",
                "delete from project_phases where project_id in {$p}",
                "delete from project_comments where project_id in {$p}",
                "delete from project_attachments where project_id in {$p}",
                "delete from project_activities where project_id in {$p}",
                "update ad_campaigns set project_id = null where project_id in {$p}",
                "delete from projects where client_id in {$c}",
                "delete from macro_plan_comments where macro_plan_id in {$m}",
                "delete from macro_plan_attachments where macro_plan_id in {$m}",
                "delete from macro_plan_activities where macro_plan_id in {$m}",
                "delete from meeting_participants where meeting_id in (select id from meetings where client_id in {$c})",
                "delete from meeting_attachments where meeting_id in (select id from meetings where client_id in {$c})",
                "delete from meetings where client_id in {$c}",
                "delete from macro_plans where client_id in {$c}",
                "delete from client_lead_opportunity_notes where client_lead_opportunity_id in {$o}",
                "delete from client_lead_opportunities where client_lead_id in {$l}",
                "delete from service_messages where conversation_id in (select id from service_conversations where client_id in {$c})",
                "delete from service_conversations where client_id in {$c}",
                "delete from client_leads where client_id in {$c}",
                "delete from client_lead_sources where client_id in {$c}",
                "update service_diagnostic_recommendations set previous_recommendation_id = null where diagnostic_id in {$d}",
                "delete from service_diagnostic_recommendations where diagnostic_id in {$d}",
                "delete from service_diagnostic_personas where diagnostic_id in {$d}",
                "delete from service_diagnostics where client_id in {$c}",
                "delete from client_integrations where client_id in {$c}",
                "delete from ad_daily_snapshots where organization_id = '{$id}'",
                "delete from campaign_logs where organization_id = '{$id}'",
                "delete from campaign_insights where client_id in {$c}",
                "delete from ad_ads where organization_id = '{$id}'",
                "delete from ad_adsets where organization_id = '{$id}'",
                "delete from ad_campaigns where organization_id = '{$id}'",
                "delete from client_ad_billing_documents where client_ad_account_id in {$a}",
                "delete from ad_account_statements where client_ad_account_id in {$a}",
                "delete from ad_account_connections where client_ad_account_id in {$a}",
                "delete from ad_statement_deliveries where client_id in {$c}",
                "delete from client_ad_budgets where client_id in {$c}",
                "delete from client_ad_accounts where client_id in {$c}",
                "delete from client_credentials where client_id in {$c}",
                "delete from client_links where client_id in {$c}",
                "delete from client_modules where client_id in {$c}",
                "delete from client_contacts where client_id in {$c}",
            ] as $sql) {
                $this->tryStatement($db, $sql);
            }

            // Demais tabelas com organization_id (avisos, pendências, logs…), até não sobrar nada.
            $tables = collect($db->select("select table_name from information_schema.columns where table_schema = 'public' and column_name = 'organization_id' and table_name not in ('organizations', 'organization_users', 'clients', 'contacts')"))
                ->pluck('table_name')->all();
            for ($pass = 0; $pass < 5 && $tables; $pass++) {
                $tables = array_values(array_filter($tables, fn ($table) => !$this->tryStatement($db, "delete from {$table} where organization_id = '{$id}'")));
            }

            $this->tryStatement($db, "delete from contacts where organization_id = '{$id}'");
            $this->tryStatement($db, "delete from clients where organization_id = '{$id}'");

            // Usuários fictícios (só os que existem apenas na demo); super admins ficam.
            $db->statement("create temp table demo_users on commit drop as select user_id from organization_users ou where organization_id = '{$id}' and not exists (select 1 from organization_users o2 where o2.user_id = ou.user_id and o2.organization_id <> '{$id}')");
            $db->statement("delete from organization_users where organization_id = '{$id}'");
            $db->statement("delete from users where id in (select user_id from demo_users) and email like '%@demo.nonna.invalid'");
            $db->statement("delete from organizations where id = '{$id}'");
        });

        Storage::disk('r2')->deleteDirectory(self::R2_DIR);
    }

    /** Roda dentro de um savepoint: tabela que não existe ou FK pendente não aborta a transação. */
    private function tryStatement($db, string $sql): bool
    {
        try {
            $db->transaction(fn () => $db->statement($sql));

            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────── helpers

    /** @template T of Model @param class-string<T> $class @return T */
    private function mk(string $class, array $attrs): Model
    {
        $model = new $class();
        if (!$model->getIncrementing() && !array_key_exists($model->getKeyName(), $attrs)) {
            $attrs[$model->getKeyName()] = (string) Str::orderedUuid();
        }
        // Grava sem eventos (o Tenantable não roda): preenche a organização aqui.
        $table = $model->getTable();
        $this->orgColumn[$table] ??= \Illuminate\Support\Facades\Schema::connection('pgsql')->hasColumn($table, 'organization_id');
        if ($this->orgColumn[$table] && isset($this->org) && !array_key_exists('organization_id', $attrs)) {
            $attrs['organization_id'] = $this->org->id;
        }
        $model->forceFill($attrs);
        $model->save();

        return $model;
    }

    private function d(int $days): Carbon
    {
        return $this->today->copy()->addDays($days);
    }

    private function days(int $days, string $time = '10:00'): Carbon
    {
        return $this->today->copy()->addDays($days)->setTimeFromTimeString($time);
    }

    private function say(string $msg): void
    {
        if ($this->log) {
            ($this->log)($msg);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Models\ClientCredential;
use App\Models\ClientDiagnostic;
use App\Models\ClientContact;
use App\Models\Contact;
use App\Models\DiagnosticCompetitor;
use App\Models\DiagnosticPersona;
use App\Models\MacroPlan;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── USUÁRIOS ───────────────────────────────────────────────────────
        $evandro = User::firstOrCreate(
            ['email' => 'evandro@nonna.com.br'],
            ['name' => 'Evandro Bariviera', 'password' => Hash::make('password')]
        );

        $camila = User::firstOrCreate(
            ['email' => 'camila@nonna.com.br'],
            ['name' => 'Camila Gomes', 'password' => Hash::make('password')]
        );

        $rafael = User::firstOrCreate(
            ['email' => 'rafael@nonna.com.br'],
            ['name' => 'Rafael Silva', 'password' => Hash::make('password')]
        );

        $mariana = User::firstOrCreate(
            ['email' => 'mariana@nonna.com.br'],
            ['name' => 'Mariana Luz', 'password' => Hash::make('password')]
        );

        // ── CONTATOS (leads/prospects) ─────────────────────────────────────
        $contatos = [];

        $contatos['joao'] = Contact::create([
            'name'         => 'João Almeida',
            'email'        => 'joao.almeida@brilhonatural.com.br',
            'phone'        => '(11) 98765-4321',
            'whatsapp'     => '(11) 98765-4321',
            'job_title'    => 'CEO',
            'company_name' => 'Brilho Natural Cosméticos',
            'source'       => 'indicacao',
            'status'       => 'ganho',
            'notes'        => 'Indicação da Fernanda da DentMax. Muito focado em crescimento via tráfego pago.',
            'assigned_to'  => $evandro->id,
            'created_by'   => $evandro->id,
        ]);

        $contatos['patricia'] = Contact::create([
            'name'         => 'Patrícia Mendes',
            'email'        => 'patricia@dentmaxodonto.com.br',
            'phone'        => '(11) 3344-5566',
            'whatsapp'     => '(11) 97788-9900',
            'job_title'    => 'Sócia-Diretora',
            'company_name' => 'DentMax Odontologia',
            'source'       => 'instagram',
            'status'       => 'ganho',
            'notes'        => 'Chegou pelo Instagram. Quer fortalecer presença online e captação de novos pacientes.',
            'assigned_to'  => $camila->id,
            'created_by'   => $camila->id,
        ]);

        $contatos['rodrigo'] = Contact::create([
            'name'         => 'Rodrigo Volare',
            'email'        => 'rodrigo@volareventos.com.br',
            'phone'        => '(41) 99876-5432',
            'whatsapp'     => '(41) 99876-5432',
            'job_title'    => 'Fundador',
            'company_name' => 'Volare Eventos',
            'source'       => 'linkedin',
            'status'       => 'ganho',
            'notes'        => 'Precisa de estratégia completa para temporada de festas de fim de ano.',
            'assigned_to'  => $evandro->id,
            'created_by'   => $evandro->id,
        ]);

        $contatos['fernanda'] = Contact::create([
            'name'         => 'Fernanda Costa',
            'email'        => 'fernanda@inovaclinica.com.br',
            'phone'        => '(11) 95555-1234',
            'whatsapp'     => '(11) 95555-1234',
            'job_title'    => 'Gerente de Marketing',
            'company_name' => 'InovaClínica Estética',
            'source'       => 'whatsapp',
            'status'       => 'em_negociacao',
            'notes'        => 'Enviou proposta. Está avaliando outros fornecedores.',
            'assigned_to'  => $camila->id,
            'created_by'   => $camila->id,
        ]);

        $contatos['lucas'] = Contact::create([
            'name'         => 'Lucas Tavares',
            'email'        => 'ltavares@grupofortune.com.br',
            'phone'        => '(21) 98001-7777',
            'whatsapp'     => '(21) 98001-7777',
            'job_title'    => 'Diretor Comercial',
            'company_name' => 'Grupo Fortune Imóveis',
            'source'       => 'evento',
            'status'       => 'qualificado',
            'notes'        => 'Conhecido no Summit de Marketing Digital. Grande potencial — lançamentos imobiliários.',
            'assigned_to'  => $evandro->id,
            'created_by'   => $evandro->id,
        ]);

        $contatos['ana'] = Contact::create([
            'name'         => 'Ana Paula Ramos',
            'email'        => 'ana@confeitariadarosa.com.br',
            'phone'        => '(51) 98888-0011',
            'whatsapp'     => '(51) 98888-0011',
            'job_title'    => 'Proprietária',
            'company_name' => 'Confeitaria da Rosa',
            'source'       => 'site',
            'status'       => 'novo',
            'notes'        => 'Preencheu formulário no site. Quer começar do zero nas redes.',
            'assigned_to'  => $camila->id,
            'created_by'   => $camila->id,
        ]);

        $contatos['marco'] = Contact::create([
            'name'         => 'Marco Aurélio Pinto',
            'email'        => 'marco@technofitness.com.br',
            'phone'        => '(11) 97654-3210',
            'whatsapp'     => '(11) 97654-3210',
            'job_title'    => 'CEO',
            'company_name' => 'TechnoFitness',
            'source'       => 'indicacao',
            'status'       => 'perdido',
            'notes'        => 'Decidiu contratar agência interna. Pode retornar no próximo trimestre.',
            'assigned_to'  => $evandro->id,
            'created_by'   => $evandro->id,
        ]);

        $contatos['bianca'] = Contact::create([
            'name'         => 'Bianca Ferreira',
            'email'        => 'bianca@smartrealty.com.br',
            'phone'        => '(11) 96543-2109',
            'whatsapp'     => '(11) 96543-2109',
            'job_title'    => 'Sócia',
            'company_name' => 'Smart Realty',
            'source'       => 'instagram',
            'status'       => 'qualificado',
            'notes'        => 'Chegou pelo anúncio de Instagram. Quer tráfego pago para captação de leads.',
            'assigned_to'  => $mariana->id,
            'created_by'   => $mariana->id,
        ]);

        // ── OPORTUNIDADES ─────────────────────────────────────────────────
        $op1 = Opportunity::create([
            'contact_id'         => $contatos['joao']->id,
            'title'              => 'Brilho Natural — Gestão Completa',
            'stage'              => 'ganho',
            'services_interest'  => ['trafego', 'social', 'criacao', 'email'],
            'proposed_fee'       => 3800.00,
            'proposed_ad_budget' => 5000.00,
            'contract_months'    => 12,
            'notes'              => 'Contrato assinado. Início em março/2026. Foco em e-commerce e branding.',
            'won_at'             => now()->subMonths(3),
            'assigned_to'        => $evandro->id,
            'created_by'         => $evandro->id,
        ]);

        $op2 = Opportunity::create([
            'contact_id'         => $contatos['patricia']->id,
            'title'              => 'DentMax — Social + Tráfego',
            'stage'              => 'ganho',
            'services_interest'  => ['trafego', 'social', 'criacao'],
            'proposed_fee'       => 2900.00,
            'proposed_ad_budget' => 3000.00,
            'contract_months'    => 6,
            'notes'              => 'Foco em captação de pacientes para implante e clareamento.',
            'won_at'             => now()->subMonths(2),
            'assigned_to'        => $camila->id,
            'created_by'         => $camila->id,
        ]);

        $op3 = Opportunity::create([
            'contact_id'         => $contatos['rodrigo']->id,
            'title'              => 'Volare Eventos — Lançamento Digital',
            'stage'              => 'ganho',
            'services_interest'  => ['social', 'criacao', 'web', 'trafego'],
            'proposed_fee'       => 4500.00,
            'proposed_ad_budget' => 8000.00,
            'contract_months'    => 3,
            'notes'              => 'Projeto de lançamento para o Festival de Inverno. Alta verba.',
            'won_at'             => now()->subMonth(),
            'assigned_to'        => $evandro->id,
            'created_by'         => $evandro->id,
        ]);

        $op4 = Opportunity::create([
            'contact_id'        => $contatos['fernanda']->id,
            'title'             => 'InovaClínica — Gestão de Redes',
            'stage'             => 'negociando',
            'services_interest' => ['social', 'criacao', 'trafego'],
            'proposed_fee'      => 2200.00,
            'proposed_ad_budget'=> 2000.00,
            'notes'             => 'Aguardando retorno sobre proposta. Segunda reunião marcada para sexta.',
            'assigned_to'       => $camila->id,
            'created_by'        => $camila->id,
        ]);

        $op5 = Opportunity::create([
            'contact_id'        => $contatos['lucas']->id,
            'title'             => 'Grupo Fortune — Lançamento Imobiliário',
            'stage'             => 'proposta_enviada',
            'services_interest' => ['trafego', 'web', 'criacao'],
            'proposed_fee'      => 6000.00,
            'proposed_ad_budget'=> 20000.00,
            'notes'             => 'Proposta enviada ontem. Escopo inclui landing page e meta ads para lançamento.',
            'assigned_to'       => $evandro->id,
            'created_by'        => $evandro->id,
        ]);

        $op6 = Opportunity::create([
            'contact_id'        => $contatos['marco']->id,
            'title'             => 'TechnoFitness — Growth Digital',
            'stage'             => 'perdido',
            'services_interest' => ['trafego', 'social'],
            'proposed_fee'      => 3200.00,
            'lost_reason'       => 'Contratou equipe interna de marketing',
            'lost_at'           => now()->subWeeks(3),
            'assigned_to'       => $evandro->id,
            'created_by'        => $evandro->id,
        ]);

        $op7 = Opportunity::create([
            'contact_id'        => $contatos['bianca']->id,
            'title'             => 'Smart Realty — Tráfego Pago',
            'stage'             => 'diagnostico_reuniao',
            'services_interest' => ['trafego'],
            'proposed_fee'      => 1800.00,
            'proposed_ad_budget'=> 4000.00,
            'notes'             => 'Reunião de diagnóstico agendada para a próxima semana.',
            'assigned_to'       => $mariana->id,
            'created_by'        => $mariana->id,
        ]);

        // ── CLIENTES ──────────────────────────────────────────────────────
        $brilho = Client::create([
            'company_name'       => 'Brilho Natural Cosméticos',
            'tax_id'             => '28.456.789/0001-55',
            'website'            => 'https://brilhonatural.com.br',
            'segment'            => 'E-commerce / Cosméticos',
            'status'             => 'active',
            'contracted_services'=> ['trafego', 'social', 'criacao', 'email'],
            'monthly_ad_budget'  => 'R$ 5.000',
            'contact_email'      => 'contato@brilhonatural.com.br',
            'contact_phone'      => '(11) 3333-4444',
            'address'            => 'Rua das Rosas, 450, Vila Mariana, São Paulo - SP',
            'zip_code'           => '04118-000',
            'responsible_name'   => 'João Almeida',
            'responsible_cpf'    => '345.678.901-23',
            'payment_method'     => 'boleto',
            'billing_day'        => 5,
            'billing_email'      => 'financeiro@brilhonatural.com.br',
            'notes'              => 'E-commerce de cosméticos naturais. Foco em Instagram e Meta Ads. Crescimento forte em Q1.',
        ]);

        $dentmax = Client::create([
            'company_name'       => 'DentMax Odontologia',
            'tax_id'             => '12.345.678/0001-90',
            'website'            => 'https://dentmaxodonto.com.br',
            'segment'            => 'Saúde / Odontologia',
            'status'             => 'active',
            'contracted_services'=> ['trafego', 'social', 'criacao'],
            'monthly_ad_budget'  => 'R$ 3.000',
            'contact_email'      => 'patricia@dentmaxodonto.com.br',
            'contact_phone'      => '(11) 3344-5566',
            'address'            => 'Av. Paulista, 1200, Sala 304, Bela Vista, São Paulo - SP',
            'zip_code'           => '01310-100',
            'responsible_name'   => 'Patrícia Mendes',
            'responsible_cpf'    => '456.789.012-34',
            'payment_method'     => 'pix',
            'billing_day'        => 10,
            'billing_email'      => 'patricia@dentmaxodonto.com.br',
            'notes'              => 'Clínica odontológica de médio porte. Dois consultórios em SP. Foco em implantes e estética dental.',
        ]);

        $volare = Client::create([
            'company_name'       => 'Volare Eventos',
            'tax_id'             => '34.567.890/0001-11',
            'website'            => 'https://volareventos.com.br',
            'segment'            => 'Entretenimento / Eventos',
            'status'             => 'active',
            'contracted_services'=> ['social', 'criacao', 'web', 'trafego'],
            'monthly_ad_budget'  => 'R$ 8.000',
            'contact_email'      => 'rodrigo@volareventos.com.br',
            'contact_phone'      => '(41) 3210-5678',
            'address'            => 'Rua XV de Novembro, 800, Centro, Curitiba - PR',
            'zip_code'           => '80020-310',
            'responsible_name'   => 'Rodrigo Volare',
            'responsible_cpf'    => '567.890.123-45',
            'payment_method'     => 'transferencia',
            'billing_day'        => 1,
            'billing_email'      => 'financeiro@volareventos.com.br',
            'billing_whatsapp'   => '(41) 99999-0001',
            'notes'              => 'Empresa de eventos corporativos e shows. Alta sazonalidade. Projeto de 3 meses para o Festival de Inverno.',
        ]);

        // Vincular oportunidades aos clientes
        $op1->update(['client_id' => $brilho->id]);
        $op2->update(['client_id' => $dentmax->id]);
        $op3->update(['client_id' => $volare->id]);

        // ── CLIENT CONTACTS (junction) ────────────────────────────────────
        ClientContact::create([
            'client_id'  => $brilho->id,
            'contact_id' => $contatos['joao']->id,
            'role'       => 'decisor',
            'is_primary' => true,
        ]);

        ClientContact::create([
            'client_id'  => $dentmax->id,
            'contact_id' => $contatos['patricia']->id,
            'role'       => 'decisor',
            'is_primary' => true,
        ]);

        ClientContact::create([
            'client_id'  => $volare->id,
            'contact_id' => $contatos['rodrigo']->id,
            'role'       => 'decisor',
            'is_primary' => true,
        ]);

        // ── CONTAS DE ANÚNCIOS ────────────────────────────────────────────
        ClientAdAccount::create([
            'client_id'    => $brilho->id,
            'platform'     => 'meta',
            'account_id'   => '428374651029384',
            'account_name' => 'Brilho Natural — Principal',
            'status'       => 'ativo',
            'created_by'   => $mariana->id,
        ]);
        ClientAdAccount::create([
            'client_id'    => $brilho->id,
            'platform'     => 'google',
            'account_id'   => '721-845-9036',
            'account_name' => 'Brilho Natural — Google Ads',
            'status'       => 'ativo',
            'created_by'   => $mariana->id,
        ]);

        ClientAdAccount::create([
            'client_id'    => $dentmax->id,
            'platform'     => 'meta',
            'account_id'   => '183746502938475',
            'account_name' => 'DentMax — Meta Ads',
            'status'       => 'ativo',
            'created_by'   => $mariana->id,
        ]);

        ClientAdAccount::create([
            'client_id'    => $volare->id,
            'platform'     => 'meta',
            'account_id'   => '937465018273645',
            'account_name' => 'Volare — Meta Ads (Festival)',
            'status'       => 'ativo',
            'created_by'   => $evandro->id,
        ]);
        ClientAdAccount::create([
            'client_id'    => $volare->id,
            'platform'     => 'tiktok',
            'account_id'   => 'TT-7384920183',
            'account_name' => 'Volare — TikTok Ads',
            'status'       => 'ativo',
            'created_by'   => $evandro->id,
        ]);

        // ── CREDENCIAIS ───────────────────────────────────────────────────
        ClientCredential::create([
            'client_id'  => $brilho->id,
            'platform'   => 'wordpress',
            'access_url' => 'https://brilhonatural.com.br/wp-admin',
            'username'   => 'admin_brilho',
            'password'   => Crypt::encrypt('Bn@2024#Secure'),
            'notes'      => 'Admin principal do WooCommerce',
            'created_by' => $evandro->id,
        ]);
        ClientCredential::create([
            'client_id'  => $brilho->id,
            'platform'   => 'meta_business',
            'access_url' => 'https://business.facebook.com',
            'username'   => 'joao.almeida@brilhonatural.com.br',
            'password'   => Crypt::encrypt('MetaBrilho2024!'),
            'notes'      => 'Acesso ao BM com permissão de admin',
            'created_by' => $evandro->id,
        ]);
        ClientCredential::create([
            'client_id'  => $brilho->id,
            'platform'   => 'google_analytics',
            'access_url' => 'https://analytics.google.com',
            'username'   => 'agencia@brilhonatural.com.br',
            'password'   => Crypt::encrypt('GA4_Brilho#2024'),
            'notes'      => 'GA4 Property ID: G-XXXXXXXX',
            'created_by' => $rafael->id,
        ]);

        ClientCredential::create([
            'client_id'  => $dentmax->id,
            'platform'   => 'instagram',
            'access_url' => 'https://instagram.com/dentmaxodonto',
            'username'   => '@dentmaxodonto',
            'password'   => Crypt::encrypt('Insta@DentMax24'),
            'notes'      => 'Perfil comercial — 2FA ativo no celular da Patricia',
            'created_by' => $camila->id,
        ]);
        ClientCredential::create([
            'client_id'  => $dentmax->id,
            'platform'   => 'meta_business',
            'access_url' => 'https://business.facebook.com',
            'username'   => 'patricia@dentmaxodonto.com.br',
            'password'   => Crypt::encrypt('BMDentMax2024@'),
            'created_by' => $camila->id,
        ]);

        ClientCredential::create([
            'client_id'  => $volare->id,
            'platform'   => 'wordpress',
            'access_url' => 'https://volareventos.com.br/wp-login.php',
            'username'   => 'volare_admin',
            'password'   => Crypt::encrypt('Volare@WP2024!'),
            'notes'      => 'Tema Divi. Contato do dev: dev@volareventos.com.br',
            'created_by' => $rafael->id,
        ]);

        // ── DIAGNÓSTICOS ──────────────────────────────────────────────────
        $diagBrilho = ClientDiagnostic::create([
            'client_id'   => $brilho->id,
            'version'     => 1,
            'title'       => 'Diagnóstico Inicial — Q1 2026',
            'status'      => 'complete',
            'completed_at'=> now()->subMonths(2),
            'created_by'  => $evandro->id,
            'sec01_briefing' => [
                'historia_empresa'   => 'Fundada em 2019, a Brilho Natural nasceu da paixão de João pela cosmetologia natural. Iniciou vendendo kits de cabelo em feiras e migrou para e-commerce em 2021.',
                'produtos_servicos'  => 'Linha completa de cosméticos naturais: shampoos, condicionadores, máscaras, óleos e cremes. Bestseller: Kit Restauração Profunda.',
                'publico_alvo'       => 'Mulheres 25-45 anos, preocupadas com ingredientes naturais e sustentabilidade. Renda média-alta.',
                'diferenciais'       => 'Fórmulas 100% naturais certificadas, embalagens eco-friendly, entrega expressa para SP.',
                'objetivos_ciclo'    => 'Aumentar ticket médio em 30%, reduzir CAC em 20%, expandir para o Nordeste.',
            ],
            'sec02_marketing' => [
                'canais_atuais'      => 'Instagram (78k seguidores), Meta Ads, e-mail marketing (lista de 12k). TikTok ainda não explorado.',
                'historico_anuncios' => 'ROAS médio de 3,2x no último trimestre. Melhor campanha: Black Friday (ROAS 5,1x).',
                'orcamento_mensal'   => 'R$ 5.000/mês em mídia. Potencial para escalar até R$ 8.000 se ROAS > 4x.',
            ],
            'sec04_competition' => [
                'contexto' => 'Mercado de cosméticos naturais crescendo 18% ao ano no Brasil. Concorrência aumentando com entrada de marcas internacionais.',
            ],
        ]);

        DiagnosticCompetitor::create([
            'diagnostic_id' => $diagBrilho->id,
            'position'      => 1,
            'name'          => 'Lola Cosmetics',
            'main_channels' => 'Instagram, YouTube, TikTok, pontos de venda físico',
            'positioning'   => 'Cosméticos divertidos e acessíveis com mascotes icônicas. Foco em cabelos cacheados.',
            'strengths'     => 'Grande brand awareness, mascotes virais, forte no YouTube e TikTok.',
            'vulnerability' => 'Preço premium, distribuição limitada fora do Sul/Sudeste.',
        ]);

        DiagnosticCompetitor::create([
            'diagnostic_id' => $diagBrilho->id,
            'position'      => 2,
            'name'          => 'Skala',
            'main_channels' => 'TikTok, supermercados, farmácias',
            'positioning'   => 'Cosméticos acessíveis com distribuição nacional capilar.',
            'strengths'     => 'Preço baixo, distribuição nacional capilar, forte no TikTok.',
            'vulnerability' => 'Percepção de produto de menor qualidade. Sem apelo de naturalidade.',
        ]);

        DiagnosticPersona::create([
            'diagnostic_id'   => $diagBrilho->id,
            'position'        => 1,
            'name'            => 'Fernanda, 32 anos',
            'age_range'       => '28-38',
            'profession'      => 'Analista de Marketing',
            'income'          => 'R$ 5.000–R$ 9.000/mês',
            'location'        => 'São Paulo e Região Metropolitana',
            'what_they_seek'  => 'Cabelo hidratado e brilhoso sem ingredientes agressivos. Quer se sentir bonita sem culpa ambiental.',
            'frustrations'    => 'Cabelo com frizz e ressecado. Produtos que prometem e não entregam. Ingredientes químicos que ela não reconhece.',
            'decision_process'=> 'Pesquisa muito antes de comprar. Assiste reviews no YouTube, lê comentários no Instagram e TikTok. Confia em UGC e antes/depois reais.',
            'objections'      => 'Preço alto. Dúvida se realmente é natural. "Já tentei muita coisa que não funcionou."',
        ]);

        $diagDentmax = ClientDiagnostic::create([
            'client_id'   => $dentmax->id,
            'version'     => 1,
            'title'       => 'Diagnóstico Digital — Jun 2026',
            'status'      => 'complete',
            'completed_at'=> now()->subMonth(),
            'created_by'  => $evandro->id,
            'sec01_briefing' => [
                'historia_empresa'  => 'DentMax foi fundada em 2015 por Patrícia Mendes. Dois consultórios em São Paulo (Paulista e Pinheiros). Especialistas em implante e estética dental.',
                'produtos_servicos' => 'Implantes dentários, lentes de contato dental, clareamento, ortodontia, emergências 24h.',
                'publico_alvo'      => 'Adultos 30-55 anos com renda acima de R$ 5.000, que valorizam saúde bucal e estética.',
                'diferenciais'      => 'Atendimento humanizado, tecnologia de ponta (escâner intraoral 3D), parcelamento facilitado.',
                'objetivos_ciclo'   => 'Gerar 50 leads qualificados/mês via digital. Aumentar agendamentos online em 40%.',
            ],
            'sec02_marketing' => [
                'canais_atuais'     => 'Instagram (12k), Facebook, Google Meu Negócio (4,8 estrelas). Sem campanhas de tráfego ativas até agora.',
                'historico_anuncios'=> 'Nunca investiu em mídia paga. Cresceu por indicação.',
                'orcamento_mensal'  => 'R$ 3.000/mês disponível para começar. Dispostos a escalar se resultados aparecerem.',
            ],
        ]);

        DiagnosticPersona::create([
            'diagnostic_id'   => $diagDentmax->id,
            'position'        => 1,
            'name'            => 'Carlos, 42 anos',
            'age_range'       => '38-52',
            'profession'      => 'Executivo / Empresário',
            'income'          => 'R$ 12.000+/mês',
            'location'        => 'São Paulo — Zona Sul e Centro',
            'what_they_seek'  => 'Sorriso bonito e natural. Quer comer bem e ter autoconfiança.',
            'frustrations'    => 'Sente vergonha do sorriso. Perdeu dentes e ainda não resolveu. Medo de dentista e de valores altos.',
            'decision_process'=> 'Pesquisa no Google, assiste depoimentos no YouTube e WhatsApp. Pede indicação para amigos. Vai em no mínimo 2 orçamentos.',
            'objections'      => '"É muito caro." "Tenho medo de dor." "Vai ficar artificial?" Precisa de garantia e parcelamento fácil.',
        ]);

        // ── REUNIÕES ──────────────────────────────────────────────────────
        $meet1 = Meeting::create([
            'title'            => 'Kick-off Estratégico — Brilho Natural',
            'type'             => 'kickoff_estrategico',
            'modality'         => 'online',
            'status'           => 'realizada',
            'client_id'        => $brilho->id,
            'organized_by'     => $evandro->id,
            'created_by'       => $evandro->id,
            'scheduled_at'     => now()->subMonths(3)->setHour(10)->setMinute(0),
            'duration_minutes' => 90,
            'online_link'      => 'https://meet.google.com/abc-defg-hij',
            'agenda'           => "1. Apresentação da equipe Nonna\n2. Revisão do diagnóstico\n3. Alinhamento de expectativas e objetivos Q1\n4. Definição de canais prioritários\n5. Próximos passos",
            'ata'              => "Reunião produtiva. João reforçou prioridade no TikTok para Q1. Definimos foco em conteúdo orgânico + Meta Ads. Budget de R$5k mantido. Rafael responsável por criar primeiros assets até 15/03. Próxima reunião: alinhamento de sprint em 15 dias.",
            'next_steps'       => "- Rafael: criar 10 peças estáticas para feed até 15/03\n- Mariana: configurar pixel e conta de anúncios até 12/03\n- Evandro: compartilhar calendário editorial com João",
            'ata_recorded_at'  => now()->subMonths(3)->addHours(2),
        ]);
        MeetingParticipant::create(['meeting_id' => $meet1->id, 'user_id' => $evandro->id]);
        MeetingParticipant::create(['meeting_id' => $meet1->id, 'user_id' => $rafael->id]);
        MeetingParticipant::create(['meeting_id' => $meet1->id, 'user_id' => $mariana->id]);

        $meet2 = Meeting::create([
            'title'            => 'Alinhamento Mensal — DentMax',
            'type'             => 'alinhamento_projeto',
            'modality'         => 'online',
            'status'           => 'realizada',
            'client_id'        => $dentmax->id,
            'organized_by'     => $camila->id,
            'created_by'       => $camila->id,
            'scheduled_at'     => now()->subWeeks(2)->setHour(14)->setMinute(0),
            'duration_minutes' => 60,
            'online_link'      => 'https://meet.google.com/xyz-abcd-efg',
            'agenda'           => "1. Resultados das campanhas de maio\n2. Aprovação de peças para junho\n3. Alinhamento de promoção de Dia dos Namorados\n4. Feedback da Patrícia sobre leads gerados",
            'ata'              => "Bons resultados em maio: 38 leads gerados, 8 convertidos em consulta. Patrícia aprovou a campanha de Dia dos Namorados. Aumentar budget para R$4k em junho. Criar stories de depoimentos de pacientes.",
            'next_steps'       => "- Rafael: 5 stories de depoimentos até sexta\n- Mariana: aumentar budget e criar campanha Dia dos Namorados\n- Camila: agendar próximo alinhamento para 30 dias",
            'ata_recorded_at'  => now()->subWeeks(2)->addHour(),
        ]);
        MeetingParticipant::create(['meeting_id' => $meet2->id, 'user_id' => $camila->id]);
        MeetingParticipant::create(['meeting_id' => $meet2->id, 'user_id' => $evandro->id]);

        $meet3 = Meeting::create([
            'title'            => 'Reunião de Diagnóstico — InovaClínica',
            'type'             => 'comercial_vendas',
            'modality'         => 'presencial_agencia',
            'status'           => 'realizada',
            'organized_by'     => $camila->id,
            'created_by'       => $camila->id,
            'scheduled_at'     => now()->subDays(5)->setHour(11)->setMinute(0),
            'duration_minutes' => 60,
            'location'         => 'Escritório Nonna — Rua Augusta, 1500, Sala 201',
            'agenda'           => "1. Entender os objetivos da InovaClínica\n2. Audit das redes atuais\n3. Apresentar cases similares\n4. Proposta preliminar",
            'ata'              => "Fernanda veio acompanhada do sócio dela. Estão com 3 orçamentos em mãos. Nossa proposta ficou no meio. Ponto positivo: gostaram muito dos cases. Pediu prazo de 1 semana para decidir.",
            'next_steps'       => "- Camila: follow-up na quinta-feira\n- Evandro: preparar case específico de clínica estética",
            'ata_recorded_at'  => now()->subDays(5)->addHours(2),
        ]);
        MeetingParticipant::create(['meeting_id' => $meet3->id, 'user_id' => $camila->id]);
        MeetingParticipant::create(['meeting_id' => $meet3->id, 'user_id' => $evandro->id]);

        $meet4 = Meeting::create([
            'title'            => 'Sprint Planning — Volare Eventos',
            'type'             => 'distribuicao_sprint',
            'modality'         => 'online',
            'status'           => 'agendada',
            'client_id'        => $volare->id,
            'organized_by'     => $evandro->id,
            'created_by'       => $evandro->id,
            'scheduled_at'     => now()->addDays(2)->setHour(9)->setMinute(0),
            'duration_minutes' => 45,
            'online_link'      => 'https://meet.google.com/volare-sprint-01',
            'agenda'           => "1. Revisão do macroplanejamento do Festival\n2. Distribuição de tarefas — semana 1\n3. Alinhamento de prazos com Rodrigo\n4. Definir entregáveis até a próxima sexta",
        ]);
        MeetingParticipant::create(['meeting_id' => $meet4->id, 'user_id' => $evandro->id]);
        MeetingParticipant::create(['meeting_id' => $meet4->id, 'user_id' => $rafael->id]);
        MeetingParticipant::create(['meeting_id' => $meet4->id, 'user_id' => $mariana->id]);
        MeetingParticipant::create(['meeting_id' => $meet4->id, 'user_id' => $camila->id]);

        $meet5 = Meeting::create([
            'title'            => 'Sync Interno — Equipe Nonna',
            'type'             => 'setor_sync',
            'modality'         => 'presencial_agencia',
            'status'           => 'para_agendar',
            'organized_by'     => $evandro->id,
            'created_by'       => $evandro->id,
            'scheduled_at'     => now()->addWeek()->setHour(8)->setMinute(30),
            'duration_minutes' => 30,
            'location'         => 'Escritório Nonna — Sala de Reunião',
            'agenda'           => "1. Check de todos os clientes ativos\n2. Gargalos e impedimentos\n3. Prioridades da semana\n4. Avisos gerais",
        ]);
        MeetingParticipant::create(['meeting_id' => $meet5->id, 'user_id' => $evandro->id]);
        MeetingParticipant::create(['meeting_id' => $meet5->id, 'user_id' => $camila->id]);
        MeetingParticipant::create(['meeting_id' => $meet5->id, 'user_id' => $rafael->id]);
        MeetingParticipant::create(['meeting_id' => $meet5->id, 'user_id' => $mariana->id]);

        // ── MACROPLANEJAMENTOS ────────────────────────────────────────────

        // ── Brilho Natural — Q2 2026 ──
        $macroBrilho = MacroPlan::create([
            'client_id'      => $brilho->id,
            'responsible_id' => $evandro->id,
            'created_by'     => $evandro->id,
            'title'          => 'Q2 2026 — Escala e Fidelização',
            'period_start'   => '2026-04-01',
            'period_end'     => '2026-06-30',
            'status'         => 'active',
            'bloco1' => [
                'foco_principal'    => 'Escalar vendas via Meta Ads e lançar canal TikTok. Objetivo: ROAS > 4,5x e 30% de crescimento em receita recorrente (assinaturas).',
                'verba_anuncios'    => 'R$ 5.000/mês (Meta: R$ 3.500 | Google: R$ 1.500)',
                'metas_indicadores' => "- ROAS Meta Ads ≥ 4,5x\n- 500 novos seguidores TikTok (mês 1)\n- Taxa de recompra ≥ 35%\n- CAC < R$ 45\n- Ticket médio ≥ R$ 187",
            ],
            'bloco2' => [
                'desafio_atual'       => 'CAC subiu 15% no último trimestre por conta da concorrência crescente. Algoritmo do Instagram favorecendo cada vez mais vídeos curtos.',
                'estrategia'          => "Pilar 1: Vídeos curtos (Reels + TikTok) para topo de funil\nPilar 2: UGC — ativar embaixadores orgânicos entre as 500 clientes mais engajadas\nPilar 3: E-mail marketing para recompra — fluxo de recuperação pós-compra",
                'pilares_comunicacao' => "1. Naturalidade que funciona — resultados reais, sem filtro excessivo\n2. Ingredientes que você conhece — transparência de fórmula\n3. Sustentabilidade que faz sentido — cada compra planta uma árvore",
            ],
            'bloco4' => [
                'trafego_continuo'   => "- Otimização semanal de campanhas Meta Ads\n- A/B test de criativos: mínimo 2 por semana\n- Relatório de performance quinzenal para João",
                'social_continuo'    => "- 5 posts/semana no Instagram (3 reels + 2 estáticos)\n- 3 vídeos/semana no TikTok\n- Stories diários com produtos e bastidores",
                'outras_demandas'    => "- E-mail semanal para base (Thursday Newsletter)\n- Revisão mensal de SEO on-page\n- Relatório mensal consolidado até dia 5",
            ],
            'bloco5' => [
                'acessos'   => "✓ Pixel Meta instalado\n✓ GA4 configurado\n✓ GTM ativo\n✓ API de Conversões configurada\n⚠ Google Ads: pendente vinculação com Google Analytics",
                'materiais' => "✓ Manual de marca completo\n✓ Banco de fotos Q1 (120 imagens)\n⚠ Precisamos de 20 vídeos UGC de clientes — João vai solicitar",
                'pendencias'=> "- João precisa assinar adendo contratual para o canal TikTok\n- Acesso ao Shopify ainda pendente (Rafael)",
            ],
        ]);

        // Projetos do Brilho Natural
        $proj1 = Project::create([
            'macro_plan_id'   => $macroBrilho->id,
            'client_id'       => $brilho->id,
            'position'        => 1,
            'title'           => 'Lançamento Canal TikTok',
            'objective'       => 'Criar e lançar o canal oficial da Brilho Natural no TikTok com identidade visual definida e primeiros 10 vídeos publicados.',
            'disciplines'     => ['criacao', 'social'],
            'briefing_criacao'=> "Vídeos verticais 9:16, máximo 60s. Estética clean e natural. Músicas trending. 3 formatos: tutorial de cabelo, before/after e bastidores da fábrica. Cores: verde musgo + bege + branco.\n\nNão usar filtros pesados. Autenticidade é o principal ativo.",
            'briefing_social' => "Postar nos primeiros 5 dias às 18h30 (horário de pico do público). Usar todos os trending hashtags relevantes. Responder 100% dos comentários nas primeiras 48h. Meta: 500 seguidores na primeira semana.",
            'status'          => 'active',
        ]);

        $proj2 = Project::create([
            'macro_plan_id'   => $macroBrilho->id,
            'client_id'       => $brilho->id,
            'position'        => 2,
            'title'           => 'Campanha Dia dos Namorados',
            'objective'       => 'Criar campanha completa para o Dia dos Namorados com foco em kits presentes e impulsionamento via Meta Ads.',
            'disciplines'     => ['criacao', 'trafego'],
            'briefing_criacao'=> "Kits especiais com laço e embalagem presente. Conceito: \"O presente que cuida de quem você ama\". Cores: rosa claro + dourado. Formatos: stories, carrossel feed, banner site e e-mail.\n\nPrecisamos de ao menos 3 fotos com casal usando os produtos.",
            'briefing_trafego'=> "Campanha de Conversões no catálogo. Retargeting para quem visitou o site nos últimos 30 dias. Lookkalike 1% baseado em compradores Q1. Budget: R$ 2.500 em 10 dias. CPA alvo: R$ 80.",
            'status'          => 'active',
        ]);

        $proj3 = Project::create([
            'macro_plan_id'   => $macroBrilho->id,
            'client_id'       => $brilho->id,
            'position'        => 3,
            'title'           => 'Fluxo de E-mail Recompra',
            'objective'       => 'Criar sequência de e-mails automáticos pós-compra para aumentar taxa de recompra em 20%.',
            'disciplines'     => ['email', 'criacao'],
            'briefing_email'  => "Sequência: D+7 (como está o produto?), D+30 (avalie sua compra + desconto 10%), D+60 (novidades + produto complementar), D+90 (você não volta? cupom 15%).\n\nFerramenta: Klaviyo. Templates já existem — adaptar para a marca.",
            'briefing_criacao'=> "Templates de e-mail seguindo identidade visual. Fundo branco, texto preto, destaques em verde musgo. Máximo 3 imagens por e-mail para não pesar.",
            'status'          => 'completed',
        ]);

        // ── DentMax — Início Digital ──
        $macroDentmax = MacroPlan::create([
            'client_id'      => $dentmax->id,
            'responsible_id' => $camila->id,
            'created_by'     => $evandro->id,
            'title'          => 'Jun–Ago 2026 — Presença Digital e Captação',
            'period_start'   => '2026-06-01',
            'period_end'     => '2026-08-31',
            'status'         => 'active',
            'bloco1' => [
                'foco_principal'    => 'Construir presença digital sólida e iniciar captação de leads via Meta Ads para os procedimentos de implante e estética dental.',
                'verba_anuncios'    => 'R$ 3.000/mês — Meta Ads (100%)',
                'metas_indicadores' => "- 50 leads qualificados/mês via formulário\n- 20 consultas agendadas/mês\n- 15% taxa de conversão lead → consulta\n- CPL < R$ 60\n- Instagram: +500 seguidores/mês",
            ],
            'bloco2' => [
                'desafio_atual'    => 'A DentMax nunca investiu em marketing digital. Depende 100% de indicações. O Instagram está desatualizado (último post há 3 meses). Concorrentes diretos já têm forte presença digital.',
                'estrategia'       => "Mês 1: Setup completo (pixel, BM, conta) + 3 semanas de conteúdo orgânico para aquecer a conta\nMês 2: Lançar campanhas de captura de leads (formulário nativo do Meta)\nMês 3: Otimizar com base em dados reais. Escalar o que funciona.",
                'pilares_comunicacao' => "1. Sorriso que transforma — foco em antes/depois reais\n2. Tecnologia + Humanidade — alta tecnologia com atendimento acolhedor\n3. Acessível para você — parcelamento facilitado, sem surpresas",
            ],
        ]);

        $proj4 = Project::create([
            'macro_plan_id'  => $macroDentmax->id,
            'client_id'      => $dentmax->id,
            'position'       => 1,
            'title'          => 'Setup Digital Completo',
            'objective'      => 'Configurar toda a infraestrutura digital: Meta Business Suite, Pixel, GA4, GTM e perfis sociais otimizados.',
            'disciplines'    => ['setup', 'trafego'],
            'briefing_setup' => "Itens obrigatórios:\n- Business Manager com conta de anúncios vinculada\n- Pixel instalado no site (via GTM)\n- API de Conversões configurada\n- GA4 com metas de conversão (formulário de contato + WhatsApp click)\n- Google Tag Manager com todas as tags\n- Google Meu Negócio atualizado (fotos, horário, serviços)",
            'briefing_trafego' => "Após setup: criar campanha de aquecimento de conta (engajamento) por 7 dias antes de ir para conversões. Budget: R$ 500.",
            'status'         => 'active',
        ]);

        $proj5 = Project::create([
            'macro_plan_id'   => $macroDentmax->id,
            'client_id'       => $dentmax->id,
            'position'        => 2,
            'title'           => 'Identidade Visual das Redes + 30 Peças Iniciais',
            'objective'       => 'Criar template visual para Instagram e produzir as primeiras 30 peças para um mês de postagens.',
            'disciplines'     => ['criacao', 'social'],
            'briefing_criacao'=> "Estilo: clean, tons de azul claro e branco, tipografia moderna. Evitar o vermelho (associado a urgência/perigo em saúde). Formatos: feed 1:1, reels thumbnail, stories, carrossel (max 5 slides).\n\nConteúdo: 40% educativo (cuidados bucais), 30% antes/depois, 20% equipe/bastidores, 10% promoções.",
            'briefing_social' => "Frequência: 1 post/dia em stories + 4 posts no feed/semana. Horários: 8h (antes do trabalho) e 12h (almoço). Hashtags: #dentmax #implantedental #sorrisoperfeito (+ variações locais SP).",
            'status'          => 'active',
        ]);

        // ── Volare Eventos — Festival de Inverno ──
        $macroVolare = MacroPlan::create([
            'client_id'      => $volare->id,
            'responsible_id' => $evandro->id,
            'created_by'     => $evandro->id,
            'title'          => 'Festival de Inverno 2026 — Lançamento',
            'period_start'   => '2026-06-01',
            'period_end'     => '2026-08-15',
            'status'         => 'active',
            'bloco1' => [
                'foco_principal'    => 'Lançar e vender os ingressos do Festival de Inverno 2026. Meta: 2.000 ingressos vendidos até 01/07 (lote 1).',
                'verba_anuncios'    => 'R$ 8.000/mês (Meta: R$ 5.000 | TikTok: R$ 2.000 | Google: R$ 1.000)',
                'metas_indicadores' => "- Lote 1 (2.000 ingressos) esgotado até 01/07\n- CPV (custo por venda) < R$ 18\n- ROAS ≥ 5x\n- 50k alcance orgânico no lançamento\n- 10k visualizações no teaser",
            ],
            'bloco2' => [
                'desafio_atual'    => 'Festival novo sem histórico. Rodrigo precisa vender o primeiro lote para financiar a produção. Janela de 30 dias para o lançamento explosivo.',
                'estrategia'       => "Fase 1 (Semana 1-2): Teaser e construção de antecipação — sem revelar o line-up\nFase 2 (Semana 3): Reveal do line-up + abertura de vendas Lote 1\nFase 3 (Semana 4+): Sustentação com depoimentos, cobertura e provas sociais",
                'pilares_comunicacao' => "1. Experiência única — o melhor festival de inverno do Paraná\n2. Memória que dura — momentos inesquecíveis com quem você ama\n3. Urgência real — ingressos limitados, preços que sobem",
            ],
        ]);

        $proj6 = Project::create([
            'macro_plan_id'   => $macroVolare->id,
            'client_id'       => $volare->id,
            'position'        => 1,
            'title'           => 'Landing Page do Festival',
            'objective'       => 'Criar landing page de alta conversão para venda de ingressos com integração Sympla.',
            'disciplines'     => ['web', 'criacao'],
            'briefing_web'    => "Stack: WordPress + Elementor (template já existe). Integração com Sympla via iframe. Pixel Meta + GTM instalados. Formulário de interesse (email capture) antes da abertura.\n\nPágina deve carregar em < 2s. Mobile first — 70% do tráfego esperado é mobile.",
            'briefing_criacao'=> "Visual: escuro, cinematográfico, inverno. Cores: azul profundo + dourado + branco. Tipografia bold. Vídeo hero de 20s (Rodrigo vai fornecer os takes). Seções: hero, artistas, programação, ingressos, FAQ, patrocinadores.",
            'status'          => 'active',
        ]);

        $proj7 = Project::create([
            'macro_plan_id'   => $macroVolare->id,
            'client_id'       => $volare->id,
            'position'        => 2,
            'title'           => 'Campanha de Lançamento',
            'objective'       => 'Executar campanha completa de lançamento com teaser, reveal e sustentação através de Meta Ads + TikTok Ads.',
            'disciplines'     => ['trafego', 'criacao', 'social'],
            'briefing_trafego'=> "Fase teaser: campanha de engajamento (vídeo) R$ 2.000\nFase reveal: campanha de conversões R$ 4.000 no Meta + R$ 1.500 no TikTok\nRetargeting para quem assistiu 75% do teaser\nPúblico: 18-40 anos, Curitiba + Região, interesses em música, shows, festivais",
            'briefing_criacao'=> "Teaser: vídeo 30s sem revelar artistas. Atmosfera de inverno, neve artificial, fogueira. CTA: \"Em breve — salve a data\".\nReveal: vídeo 60s com artistas confirmados + confete digital. Clima de celebração.\nAds estáticos: 5 formatos (stories, feed, carrossel, banner site)",
            'briefing_social' => "Countdown no Instagram a partir de D-10. Posts diários em stories. TikTok: 1 vídeo/dia na semana do reveal. Explorar trend de 'expectativa vs realidade' com o local do festival.",
            'status'          => 'active',
        ]);

        // ── TAREFAS ───────────────────────────────────────────────────────

        // Projeto 1: TikTok Brilho Natural
        $this->criarTarefas($proj1, $evandro->id, $rafael->id, [
            ['Criar conta TikTok Business da Brilho Natural', 'setup', 'concluido', null, $rafael->id],
            ['Definir identidade visual do canal (cores, fontes, bio)', 'criacao', 'concluido', null, $rafael->id],
            ['Produzir vídeo #1 — Tutorial: Máscara Restauradora em casa', 'criacao', 'em_producao', now()->addDays(3), $rafael->id],
            ['Produzir vídeo #2 — Before/After transformação de cabelo', 'criacao', 'pronto_producao', now()->addDays(5), $rafael->id],
            ['Produzir vídeo #3 — Bastidores da fábrica em Campinas', 'criacao', 'em_copy', now()->addDays(7), $rafael->id],
            ['Criar thumbnail padrão para os primeiros 10 vídeos', 'criacao', 'revisao', now()->addDays(4), $rafael->id],
            ['Publicar e monitorar engajamento primeira semana', 'social', 'backlog', now()->addDays(10), $mariana->id],
        ]);

        // Projeto 2: Dia dos Namorados
        $this->criarTarefas($proj2, $evandro->id, $rafael->id, [
            ['Briefing fotográfico com João — kits presentes', 'criacao', 'concluido', null, $rafael->id],
            ['Ensaio fotográfico: casais + produtos', 'criacao', 'concluido', null, $rafael->id],
            ['Criar 5 peças estáticas para feed (carrossel + isoladas)', 'criacao', 'aguardando_resposta', now()->addDays(2), $rafael->id],
            ['Criar 6 stories animados para campanha', 'criacao', 'revisao', now()->addDays(3), $rafael->id],
            ['Criar banner site + e-mail marketing', 'criacao', 'backlog', now()->addDays(5), $rafael->id],
            ['Configurar campanha Meta Ads — Dia dos Namorados', 'trafego', 'backlog', now()->addDays(4), $mariana->id],
            ['Criar segmentação e públicos para campanha', 'trafego', 'backlog', now()->addDays(3), $mariana->id],
            ['Subir landing page promocional no Shopify', 'web', 'backlog', now()->addDays(6), $rafael->id],
        ]);

        // Projeto 3: Fluxo E-mail (concluído)
        $this->criarTarefas($proj3, $evandro->id, $rafael->id, [
            ['Mapear jornada pós-compra no Klaviyo', 'email', 'concluido', null, $camila->id],
            ['Criar copy dos 4 e-mails da sequência', 'email', 'concluido', null, $camila->id],
            ['Desenvolver templates HTML dos e-mails', 'criacao', 'concluido', null, $rafael->id],
            ['Configurar automação no Klaviyo', 'setup', 'concluido', null, $rafael->id],
            ['Testar fluxo com e-mails internos', 'email', 'concluido', null, $camila->id],
            ['Ativar fluxo e monitorar por 7 dias', 'email', 'concluido', null, $camila->id],
        ]);

        // Projeto 4: Setup DentMax
        $this->criarTarefas($proj4, $camila->id, $mariana->id, [
            ['Criar Business Manager e conta de anúncios', 'setup', 'concluido', null, $mariana->id],
            ['Instalar Pixel Meta via GTM no site', 'setup', 'concluido', null, $mariana->id],
            ['Configurar API de Conversões (server-side)', 'setup', 'em_producao', now()->addDays(2), $mariana->id],
            ['Configurar GA4 + eventos de conversão', 'setup', 'em_producao', now()->addDays(3), $mariana->id],
            ['Atualizar Google Meu Negócio (fotos, horário, serviços)', 'setup', 'revisao', now()->addDays(4), $camila->id],
            ['Criar campanha de aquecimento de conta (engajamento)', 'trafego', 'backlog', now()->addDays(7), $mariana->id],
            ['Testar e validar todos os eventos de conversão', 'setup', 'backlog', now()->addDays(5), $mariana->id],
        ]);

        // Projeto 5: Identidade Visual DentMax
        $this->criarTarefas($proj5, $camila->id, $rafael->id, [
            ['Criar guia de estilo visual para Instagram', 'criacao', 'concluido', null, $rafael->id],
            ['Desenvolver templates: feed 1:1 (3 variações)', 'criacao', 'concluido', null, $rafael->id],
            ['Desenvolver templates: stories (2 variações)', 'criacao', 'aguardando_resposta', now()->subDays(1), $rafael->id],
            ['Desenvolver templates: carrossel 5 slides', 'criacao', 'revisao', now()->addDays(2), $rafael->id],
            ['Criar 10 posts educativos (cuidados bucais)', 'criacao', 'em_producao', now()->addDays(5), $rafael->id],
            ['Criar 8 posts antes/depois (usar casos reais da clínica)', 'criacao', 'em_copy', now()->addDays(7), $rafael->id],
            ['Criar 12 stories para primeira semana', 'criacao', 'backlog', now()->addDays(8), $rafael->id],
            ['Agendar todos os posts na ferramenta de agendamento', 'social', 'backlog', now()->addDays(10), $camila->id],
        ]);

        // Projeto 6: Landing Page Volare
        $this->criarTarefas($proj6, $evandro->id, $rafael->id, [
            ['Wireframe da landing page (Figma)', 'web', 'concluido', null, $rafael->id],
            ['Aprovação do wireframe com Rodrigo', 'web', 'concluido', null, $evandro->id],
            ['Desenvolver layout no Elementor', 'web', 'em_producao', now()->addDays(3), $rafael->id],
            ['Criar assets visuais: hero, seções, ícones', 'criacao', 'em_producao', now()->addDays(4), $rafael->id],
            ['Editar vídeo hero 20s fornecido por Rodrigo', 'criacao', 'revisao', now()->addDays(2), $rafael->id],
            ['Integrar iframe Sympla (venda de ingressos)', 'web', 'backlog', now()->addDays(5), $rafael->id],
            ['Instalar Pixel + GTM + GA4 na landing page', 'setup', 'backlog', now()->addDays(5), $mariana->id],
            ['Otimização mobile e teste de velocidade', 'web', 'backlog', now()->addDays(7), $rafael->id],
            ['Revisão final com Rodrigo + publicar', 'web', 'backlog', now()->addDays(8), $evandro->id],
        ]);

        // Projeto 7: Campanha Volare
        $this->criarTarefas($proj7, $evandro->id, $mariana->id, [
            ['Criar vídeo teaser 30s (sem revelar artistas)', 'criacao', 'em_producao', now()->addDays(2), $rafael->id],
            ['Criar 5 posts para countdown D-10', 'criacao', 'em_copy', now()->addDays(3), $rafael->id],
            ['Montar públicos e segmentações no Meta', 'trafego', 'em_producao', now()->addDays(1), $mariana->id],
            ['Configurar campanha teaser (engajamento de vídeo)', 'trafego', 'revisao', now()->addDays(2), $mariana->id],
            ['Criar vídeo reveal 60s com artistas', 'criacao', 'backlog', now()->addDays(10), $rafael->id],
            ['Criar ads estáticos para reveal (5 formatos)', 'criacao', 'backlog', now()->addDays(11), $rafael->id],
            ['Configurar campanha de conversões pós-reveal', 'trafego', 'backlog', now()->addDays(12), $mariana->id],
            ['Criar campanha TikTok Ads', 'trafego', 'backlog', now()->addDays(12), $mariana->id],
            ['Monitorar e otimizar durante a semana do reveal', 'trafego', 'backlog', now()->addDays(14), $mariana->id],
        ]);
    }

    private function criarTarefas(Project $project, int $createdBy, int $defaultExecutor, array $tarefas): void
    {
        foreach ($tarefas as [$title, $type, $status, $dueDate, $executorId]) {
            Task::create([
                'project_id'    => $project->id,
                'macro_plan_id' => $project->macro_plan_id,
                'client_id'     => $project->client_id,
                'title'         => $title,
                'task_type'     => $type,
                'status'        => $status,
                'executor_id'   => $executorId,
                'due_date'      => $dueDate,
                'origin'        => 'projeto',
                'created_by'    => $createdBy,
            ]);
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // Primeiros modelos com linha do tempo (2026-10-26): Onboarding de Cliente (manual
    // operacional, 5 fases) e Desenvolvimento de Site (Manual Desenvolvimento de Sites da
    // Nonna). Prazos em dias a partir do início do projeto — estimativa inicial, o time ajusta
    // em Playbooks. Não mexe em playbook que já exista com o mesmo nome.
    public function up(): void
    {
        foreach (DB::connection('pgsql')->table('organizations')->pluck('id') as $orgId) {
            $roles = DB::connection('pgsql')->table('functional_roles')
                ->where('organization_id', $orgId)->pluck('id', 'name');

            foreach ($this->playbooks() as $pb) {
                $this->create($orgId, $roles, $pb);
            }
        }
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('project_playbooks')
            ->whereIn('name', array_column($this->playbooks(), 'name'))
            ->delete();
    }

    private function create(string $orgId, $roles, array $pb): void
    {
        $db = DB::connection('pgsql');
        if ($db->table('project_playbooks')->where('organization_id', $orgId)->where('name', $pb['name'])->exists()) {
            return;
        }

        $now = now();
        $playbookId = (string) Str::uuid();
        $db->table('project_playbooks')->insert([
            'id'              => $playbookId,
            'organization_id' => $orgId,
            'name'            => $pb['name'],
            'description'     => $pb['description'],
            'disciplines'     => json_encode($pb['disciplines']),
            'is_active'       => true,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        $taskPos = 0;
        foreach ($pb['phases'] as $i => $phase) {
            $phaseId = (string) Str::uuid();
            $milestone = !empty($phase['milestone']);
            $db->table('project_playbook_phases')->insert([
                'id'                  => $phaseId,
                'project_playbook_id' => $playbookId,
                'position'            => $i + 1,
                'name'                => $phase['name'],
                'is_milestone'        => $milestone,
                'start_offset_days'   => $phase['start'],
                'duration_days'       => $milestone ? null : $phase['days'],
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            foreach ($phase['tasks'] ?? [] as $t) {
                $db->table('project_playbook_tasks')->insert([
                    'id'                        => (string) Str::uuid(),
                    'project_playbook_id'       => $playbookId,
                    'project_playbook_phase_id' => $phaseId,
                    'position'                  => ++$taskPos,
                    'title'                     => $t[0],
                    'description'               => $t[4] ?? null,
                    'task_type'                 => $t[1],
                    'destination'               => $pb['destination'] ?? null,
                    'priority'                  => null,
                    'due_offset_days'           => null, // vence no fim da fase
                    'functional_role_id'        => $roles[$t[2]] ?? null,
                    'created_at'                => $now,
                    'updated_at'                => $now,
                ]);
            }
        }
    }

    // [título, tipo, papel funcional, (reservado), descrição]
    private function playbooks(): array
    {
        return [
            [
                'name'        => 'Onboarding de Cliente',
                'description' => 'Entrada de cliente novo: do ganho até o cliente virar Ativo (5 fases do manual operacional).',
                'disciplines' => ['estrategia', 'setup', 'relacionamento'],
                'destination' => null,
                'phases'      => [
                    ['name' => 'Fase 1 — Boas-vindas e automações (Dia 0)', 'start' => 0, 'days' => 1, 'tasks' => [
                        ['Conferir WhatsApp de boas-vindas enviado', 'reunioes', 'Atendimento', null, 'Normalmente sai sozinho pela esteira de ganho. Confirme que o cliente recebeu.'],
                        ['Enviar link de cadastro e conferir preenchimento', 'administrativo', 'Atendimento', null, 'O cadastro do cliente precisa estar completo pra gerar o contrato.'],
                        ['Gerar contrato e abrir ticket "Contrato em análise"', 'administrativo', 'Administrativo e Financeiro'],
                        ['Criar pasta do cliente no Drive', 'administrativo', 'Atendimento'],
                        ['Criar grupo de WhatsApp do cliente', 'reunioes', 'Atendimento'],
                    ]],
                    ['name' => 'Fase 2 — Welcome call e acessos (Dias 1-2)', 'start' => 1, 'days' => 2, 'tasks' => [
                        ['Realizar welcome call e apresentar as regras da agência', 'reunioes', 'Atendimento'],
                        ['Coletar acessos e senhas (Ficha de Acessos)', 'setup', 'Atendimento', null, 'Business Manager, contas de anúncio, Google, site/hospedagem, domínio, redes sociais.'],
                        ['Enviar contrato assinado e manual do cliente', 'administrativo', 'Atendimento'],
                        ['Agendar o kick-off estratégico', 'reunioes', 'Atendimento'],
                    ]],
                    ['name' => 'Fase 3 — Setup técnico (Dias 3-4)', 'start' => 3, 'days' => 2, 'tasks' => [
                        ['Auditar o Business Manager', 'setup', 'Tráfego'],
                        ['Configurar Pixel e API de Conversões', 'setup', 'Tráfego'],
                        ['Instalar GTM e configurar GA4', 'setup', 'Tráfego'],
                        ['Criar dashboard no Looker Studio', 'setup', 'Tráfego'],
                    ]],
                    ['name' => 'Fase 4 — Kick-off estratégico (Dia 5)', 'start' => 5, 'days' => 1, 'tasks' => [
                        ['Realizar kick-off estratégico e imersão no negócio', 'reunioes', 'Estratégia', null, 'Evandro + dono da conta. Registrar a reunião na agenda do App pra gerar a ATA.'],
                    ]],
                    ['name' => 'Fase 5 — Macroplanejamento e sprint', 'start' => 6, 'days' => 10, 'tasks' => [
                        ['Criar o macroplanejamento', 'estrategia', 'Estratégia'],
                        ['Criar campanhas/projetos e preencher o backlog', 'estrategia', 'Gestor de Projetos'],
                        ['Colocar as primeiras tarefas na sprint', 'estrategia', 'Gestor de Projetos'],
                    ]],
                    ['name' => 'Cliente Ativo', 'milestone' => true, 'start' => 16],
                ],
            ],
            [
                'name'        => 'Desenvolvimento de Site',
                'description' => 'Site em WordPress/Elementor, do briefing à entrega (Manual Desenvolvimento de Sites).',
                'disciplines' => ['web', 'setup', 'seo'],
                'destination' => 'projeto_web',
                'phases'      => [
                    ['name' => 'Briefing', 'start' => 0, 'days' => 5, 'tasks' => [
                        ['Reunião de briefing com o cliente', 'reunioes', 'Atendimento', null, 'Presencial ou online, preenchendo o Briefing Desenvolvimento de Site.'],
                        ['Captar material do cliente (logo, textos)', 'reunioes', 'Atendimento', null, 'Pedir pro cliente subir na Pasta do Cliente no Drive da Nonna. Se tiver dificuldade, a gente faz o upload.'],
                    ]],
                    ['name' => 'Setup técnico — domínio, servidor e e-mails', 'start' => 5, 'days' => 5, 'tasks' => [
                        ['Backup do site antigo', 'web', 'Dev', null, 'Se houver site antigo: baixar as imagens e colocar os textos num Google Docs, tudo na Pasta do Cliente no Drive.'],
                        ['Domínio: acesso, registro e apontamento', 'web', 'Dev', null, "Acessos que faltaram no briefing: buscar com os responsáveis e atualizar a Ficha de Acessos.\nSe precisar de apontamento, colocar o NOCOM27 como Contato Técnico.\nRegistro sempre com a titularidade do cliente informada no briefing.\nConferir o apontamento de DNS no briefing."],
                        ['Servidor: hospedagem, FTP e configuração', 'web', 'Dev', null, "Conta nova em servidor de terceiro: contratar em nome do cliente (Ficha Cadastral). Servidor da Nonna: criar conta no WHM. Conta existente: captar os acessos. Sempre atualizar a Ficha de Acessos.\nCriar a conexão FTP (Filezilla).\nPHP na versão mais atual. PHP.ini (Home Directory e domínio): allow_url_fopen off, allow_url_include off, display_errors off, file_uploads on, max_execution_time 30, max_input_time 60, max_input_vars 3000, memory_limit 512M, post_max_size 8M, upload_max_filesize 8M, zlib.output_compression on.\nDomínio já apontado: conferir o SSL (rodar o AutoSSL se precisar)."],
                        ['E-mails: migrar os existentes e criar os novos', 'web', 'Dev', null, 'Migração: de preferência configurar POP3 nas máquinas do cliente; se não der, backup da pasta mail pelo cPanel. Criar as contas e atualizar a Ficha de Acessos.'],
                    ]],
                    ['name' => 'Setup WordPress', 'start' => 10, 'days' => 3, 'tasks' => [
                        ['Instalar WordPress e criar o banco de dados', 'web', 'Dev', null, 'Última versão; banco e usuário via cPanel (atualizar a Ficha de Acessos). Configurar título, slogan e links permanentes com o nome do post.'],
                        ['Instalar plugins padrão', 'web', 'Dev', null, 'Editor Clássico, WPS Hide Login, Smush, WP Rocket, LoginPress, Elementor + Elementor PRO, Cookie Notice & Compliance for GDPR/CCPA.'],
                        ['Criar usuários e avisar o cliente', 'web', 'Dev', null, 'Atualizar a Ficha de Acessos e avisar o cliente dos dados.'],
                    ]],
                    ['name' => 'Planejamento do site', 'start' => 13, 'days' => 4, 'tasks' => [
                        ['Pré-layout: separar templates de referência', 'web', 'Design', null, 'Escolher alguns temas relacionados ao projeto e conferir se estão no ultrapack.'],
                        ['Reunião de planejamento e Mapa Conceitual do Website', 'reunioes', 'Head de Tecnologia', null, 'Com a equipe de desenvolvimento: características do site, recursos de layout e integrações.'],
                        ['Instalar plugins específicos do projeto', 'web', 'Dev', null, 'Conforme o Mapa Conceitual do Website.'],
                    ]],
                    ['name' => 'Layout e páginas', 'start' => 17, 'days' => 14, 'tasks' => [
                        ['Criar o cabeçalho (desktop, tablet e mobile)', 'web', 'Dev', null, 'Menu e logo em todos os dispositivos.'],
                        ['Criar o rodapé', 'web', 'Dev', null, 'Política de Privacidade, logo da Nonna e ícones das redes sociais.'],
                        ['Desenvolver as páginas do site', 'web', 'Dev', null, 'Conforme o Mapa Conceitual. Cada página criada vai para aprovação.'],
                        ['Revisão e ajustes das páginas', 'web', 'Dev', null, 'Ajustes pedidos voltam pra cá e, feitos, seguem de novo para aprovação do cliente.'],
                    ]],
                    ['name' => 'Integrações e conteúdo', 'start' => 31, 'days' => 7, 'tasks' => [
                        ['Integrações básicas (scripts padrão)', 'setup', 'Tráfego', null, 'Seguir o Modelo de Integração Websites (SETUP). Contas Google/Facebook sempre nos dados do cliente (Ficha de Acessos).'],
                        ['Integrações de terceiros (APIs)', 'web', 'Dev', null, 'Conforme o Mapa Conceitual do Website.'],
                        ['Cadastrar os conteúdos (textos, imagens, produtos)', 'web', 'Dev'],
                    ]],
                    ['name' => 'Marketing — SETUP e SEO', 'start' => 38, 'days' => 4, 'tasks' => [
                        ['SETUP (Básico ou Avançado)', 'setup', 'Tráfego', null, 'Conforme o Mapa Conceitual do Website.'],
                        ['SEO geral e das páginas', 'seo', 'Dev'],
                    ]],
                    ['name' => 'Testes', 'start' => 42, 'days' => 2, 'tasks' => [
                        ['Testar todas as funcionalidades', 'web', 'Dev', null, 'Formulários, WhatsApp, compras, criação de contas e links de todo o site.'],
                    ]],
                    ['name' => 'Aprovação do cliente', 'start' => 44, 'days' => 5, 'tasks' => [
                        ['Aprovação de conteúdo (imagens, funcionalidades e textos)', 'web', 'Atendimento'],
                        ['Aprovação do layout', 'web', 'Atendimento'],
                    ]],
                    ['name' => 'Site no ar', 'milestone' => true, 'start' => 49],
                    ['name' => 'Treinamento e entrega', 'start' => 49, 'days' => 5, 'tasks' => [
                        ['Gravar vídeo de treinamento', 'web', 'Dev', null, 'Tela gravada com explicações — já serve como documentação.'],
                        ['Reunião de treinamento (se o cliente quiser)', 'reunioes', 'Atendimento', null, 'Até 1h, pra estruturas mais complexas ou quando o cliente pedir.'],
                        ['Reunião de entrega do projeto', 'reunioes', 'Atendimento'],
                        ['Enviar o Formulário de Avaliação', 'reunioes', 'Atendimento'],
                    ]],
                ],
            ],
        ];
    }
};

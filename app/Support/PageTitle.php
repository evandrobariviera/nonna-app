<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * Título da aba do navegador, montado a partir da rota aberta.
 *
 * Quem trabalha com várias abas abertas precisa se achar: em vez de
 * "Nonna Agência Digital" em todas, a aba mostra "Tarefa: Post carrossel · Nonna".
 *
 * Como funciona:
 *  - ROUTES mapeia o nome da rota pra um modelo de título. "{param}" é trocado
 *    pelo nome do item aberto (o model ligado àquele parâmetro da rota).
 *  - Rota não mapeada cai no rótulo da seção (primeiro pedaço do nome da rota).
 *  - Uma tela pode forçar o próprio título com <x-slot name="pageTitle">.
 */
class PageTitle
{
    private const SUFFIX = 'Nonna';

    private const MAX_ITEM_LENGTH = 60;

    /** Telas internas (layouts.app). */
    private const ROUTES = [
        'dashboard' => 'Dashboard',

        // Tarefas / tickets
        'tasks.index' => 'Tarefas',
        'tasks.show' => 'Tarefa: {task}',
        'tickets.index' => 'Tickets',
        'tickets.create' => 'Novo ticket',

        // Planejamentos / projetos
        'macroplans.index' => 'Planejamentos',
        'macroplans.create' => 'Novo planejamento',
        'macroplans.edit' => 'Editar planejamento: {macroplan}',
        'macroplans.document.edit' => 'Documento do planejamento: {macroplan}',
        'macroplans.projects.show' => 'Projeto: {project}',
        'projects.dashboard' => 'Projetos',
        'projects.showDirect' => 'Projeto: {project}',
        'projects.document.edit' => 'Documento do projeto: {project}',
        'project-map.index' => 'Mapa de Projetos',

        // Clientes / CRM
        'clients.index' => 'Clientes',
        'clients.create' => 'Novo cliente',
        'clients.show' => 'Cliente: {client}',
        'clients.edit' => 'Editar cliente: {client}',
        'clients.production' => 'Produção: {client}',
        'clients.contracts.show' => 'Contrato: {contract}',
        'clients.dossiers.show' => 'Dossiê: {client}',
        'clients.onboarding.index' => 'Onboarding',
        'clients.offboarding.index' => 'Offboarding',
        'contacts.index' => 'Contatos',
        'contacts.create' => 'Novo contato',
        'contacts.show' => 'Contato: {contact}',
        'contacts.edit' => 'Editar contato: {contact}',
        'opportunities.index' => 'Oportunidades',
        'opportunities.create' => 'Nova oportunidade',
        'opportunities.show' => 'Oportunidade: {opportunity}',
        'opportunities.edit' => 'Editar oportunidade: {opportunity}',
        'proposals.index' => 'Propostas',
        'proposals.create' => 'Nova proposta',
        'proposals.show' => 'Proposta: {proposal}',
        'proposals.edit' => 'Editar proposta: {proposal}',
        'contracts.index' => 'Contratos',
        'catalog.index' => 'Produtos',
        'commerce.prospecting' => 'Prospecção',
        'commerce.places.show' => 'Prospecção: empresa',

        // Reuniões
        'meetings.index' => 'Agenda',
        'meetings.create' => 'Nova reunião',
        'meetings.show' => 'Reunião: {meeting}',
        'meetings.edit' => 'Editar reunião: {meeting}',
        'document-updates.show' => 'Atualização de documento',

        // Produção / sprints / filas
        'sprints.index' => 'Sprints',
        'sprints.create' => 'Nova sprint',
        'sprints.show' => 'Sprint: {sprint}',
        'sprints.board' => 'Sprint (quadro): {sprint}',
        'sprints.planning' => 'Planejar sprint: {sprint}',
        'production-panel.index' => 'Produção',
        'production-load.index' => 'Carga de Produção',
        'fila.index' => 'Filas',
        'cronograma.index' => 'Cronograma',
        'pendencies.index' => 'Pendências',
        'approvals.index' => 'Aprovações',
        'approvals.preview.central' => 'Visão do cliente: {client}',
        'approvals.preview.loose' => 'Visão do cliente: {client}',
        'approvals.preview.project' => 'Visão do cliente: {project}',

        // Campanhas / mídia
        'campaigns.index' => 'Campanhas',
        'campaigns.show' => 'Campanha: {campaign}',
        'campaign-central.today' => 'Central de Campanhas',
        'campaign-central.optimizations' => 'Otimizações',
        'campaign-creations.create' => 'Nova campanha',
        'ad-creations.index' => 'Criativos',
        'ad-creations.create' => 'Novo anúncio: {task}',
        'google-pmax.create' => 'Novo anúncio Google: {task}',
        'google-search-ads.create' => 'Anúncio de pesquisa: {campaign}',
        'ad-connections.index' => 'Contas e conexões',
        'billing-statements.index' => 'Prestação de contas',
        'billing-statements.show' => 'Prestação de contas: {client}',
        'tracking.index' => 'Rastreamento',
        'tracking.report' => 'Rastreamento: {client}',
        'orcamentos.index' => 'Orçamentos',
        'performance.index' => 'Performance',
        'performance.insights' => 'Performance · Insights',
        'performance.site' => 'Performance · Site',
        'leads.index' => 'Central de Leads',
        'leads.show' => 'Lead: {lead}',
        'service-diagnostics.index' => 'Atendimento',
        'service-diagnostics.integration' => 'Atendimento: {integration}',
        'service-diagnostics.show' => 'Diagnóstico de atendimento: {integration}',

        // Equipe / comunicação
        'chat.index' => 'Chat',
        'agent.index' => 'Agente Nonna',
        'agent.show' => 'Agente: {conversation}',
        'notifications.index' => 'Notificações',
        'productivity.index' => 'Produtividade',
        'work-monitor.index' => 'Monitor de Trabalho',
        'visoes.show' => 'Visão',

        // Ajuda / novidades
        'help.index' => 'Central de Ajuda',
        'help.create' => 'Novo artigo de ajuda',
        'help.show' => 'Ajuda: {article}',
        'help.edit' => 'Editar ajuda: {article}',
        'help.documents.index' => 'Mapa dos Documentos',
        'help.documents.show' => 'Documento: {document}',
        'help.documents.template' => 'Modelo: {document}',
        'app-updates.index' => 'Novidades',
        'app-updates.create' => 'Nova novidade',
        'app-updates.edit' => 'Editar novidade: {update}',
        'feature-suggestions.index' => 'Sugestões',

        // Configurações / automações / IA / financeiro
        'settings.index' => 'Configurações',
        'settings.notification-dispatches.index' => 'Histórico de envios',
        'automations.index' => 'Automações',
        'automations.create' => 'Nova automação',
        'automations.edit' => 'Automação: {automation}',
        'automations.logs' => 'Registros: {automation}',
        'playbooks.index' => 'Playbooks',
        'playbooks.create' => 'Novo playbook',
        'playbooks.edit' => 'Playbook: {playbook}',
        'ai.agents.index' => 'Agentes de IA',
        'ai.agents.create' => 'Novo agente de IA',
        'ai.agents.show' => 'Agente de IA: {agent}',
        'ai.agents.edit' => 'Editar agente de IA: {agent}',
        'ai.providers.index' => 'Provedores de IA',
        'ai.usage.index' => 'Uso de IA',
        'financial-dashboard.index' => 'Financeiro',
        'financial-transactions.index' => 'Lançamentos',
        'financial-categories.index' => 'Categorias financeiras',
        'profile.edit' => 'Meu perfil',
    ];

    /**
     * Portal do Cliente. Só nomes que o próprio portal já mostra pro cliente
     * (nada de apelido interno de cliente — ver Client::displayName()).
     */
    private const PORTAL_ROUTES = [
        'portal.dashboard' => 'Início',
        'portal.approvals.index' => 'Aprovações',
        'portal.approvals.loose' => 'Aprovações avulsas',
        'portal.approvals.project' => 'Aprovações: {project}',
        'portal.approvals.review' => 'Revisar aprovações',
        'portal.approvals.show' => 'Aprovação',
        'portal.service-diagnostics.index' => 'Atendimento',
        'portal.service-diagnostics.integration' => 'Atendimento',
        'portal.service-diagnostics.show' => 'Diagnóstico de atendimento',
        'portal.campaigns.index' => 'Campanhas',
        'portal.campaigns.show' => 'Campanha: {campaign}',
        'portal.leads.index' => 'Central de Leads',
        'portal.leads.show' => 'Lead: {lead}',
        'portal.tickets.index' => 'Chamados',
        'portal.tickets.create' => 'Novo chamado',
        'portal.tickets.show' => 'Chamado: {task}',
        'portal.account' => 'Minha conta',
        'portal.materials.index' => 'Materiais aprovados',
        'portal.performance.index' => 'Performance',
        'portal.performance.insights' => 'Performance · Insights',
        'portal.performance.showcase' => 'Performance',
        'portal.boletos.index' => 'Prestação de contas',
        'portal.production.index' => 'Produção',
        'portal.projects.index' => 'Projetos',
        'portal.projects.show' => 'Planejamento: {macroplan}',
        'portal.projects.document' => 'Projeto: {project}',
        'portal.site-report.index' => 'Relatório Analítico',
        'portal.meetings.index' => 'Reuniões',
        'portal.meetings.show' => 'Reunião: {meeting}',
        'portal.tasks.show' => 'Tarefa: {task}',
    ];

    /** Rótulo da seção quando a rota exata não está mapeada (primeiro pedaço do nome). */
    private const SECTIONS = [
        'tasks' => 'Tarefas', 'tickets' => 'Tickets', 'macroplans' => 'Planejamentos',
        'projects' => 'Projetos', 'clients' => 'Clientes', 'contacts' => 'Contatos',
        'opportunities' => 'Oportunidades', 'proposals' => 'Propostas', 'contracts' => 'Contratos',
        'meetings' => 'Agenda', 'sprints' => 'Sprints', 'campaigns' => 'Campanhas',
        'campaign-central' => 'Central de Campanhas', 'leads' => 'Central de Leads',
        'help' => 'Central de Ajuda', 'automations' => 'Automações', 'settings' => 'Configurações',
        'ai' => 'IA', 'agent' => 'Agente Nonna', 'performance' => 'Performance',
        'approvals' => 'Aprovações', 'pendencies' => 'Pendências', 'chat' => 'Chat',
    ];

    public static function forCurrentRoute(bool $portal = false): string
    {
        $route = request()->route();
        $label = $route instanceof Route ? self::labelFor($route, $portal) : null;

        $suffix = $portal ? 'Portal ' . self::SUFFIX : self::SUFFIX;

        return $label ? "{$label} · {$suffix}" : $suffix;
    }

    /** Monta o título de uma tela que passou o próprio texto (<x-slot name="pageTitle">). */
    public static function custom(string $label, bool $portal = false): string
    {
        $suffix = $portal ? 'Portal ' . self::SUFFIX : self::SUFFIX;
        $label = trim($label);

        return $label === '' ? $suffix : "{$label} · {$suffix}";
    }

    private static function labelFor(Route $route, bool $portal): ?string
    {
        $name = $route->getName();
        if (! $name) {
            return null;
        }

        $map = $portal ? self::PORTAL_ROUTES : self::ROUTES;
        $template = $map[$name] ?? null;

        if ($template === null) {
            $section = $portal ? null : (self::SECTIONS[Str::before($name, '.')] ?? null);

            return $section;
        }

        // Troca "{param}" pelo nome do item. Se não achar nome, corta ": {param}".
        return preg_replace_callback('/(:\s*)?\{(\w+)\}/', function ($m) use ($route, $portal) {
            $item = self::itemName($route->parameter($m[2]), $portal);

            return $item === null ? '' : $m[1] . $item;
        }, $template);
    }

    private static function itemName(mixed $value, bool $portal): ?string
    {
        if (! $value instanceof Model) {
            return null;
        }

        // Apelido de cliente é só interno — no portal nunca usar displayName().
        $name = (! $portal && method_exists($value, 'displayName'))
            ? $value->displayName()
            : ($value->getAttribute('title') ?? $value->getAttribute('name') ?? $value->getAttribute('label'));

        $name = is_string($name) ? trim(strip_tags($name)) : '';

        return $name === '' ? null : Str::limit($name, self::MAX_ITEM_LENGTH);
    }
}

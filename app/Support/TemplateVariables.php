<?php

namespace App\Support;

/**
 * Fonte única das variáveis usáveis em textos de notificação/mensagem, por
 * contexto (a entidade que dispara). Usado pelos painéis de "Variáveis
 * disponíveis" em Configurações → Mensagens Padrão e → Notificações Internas.
 *
 * Notificações internas (sino) e automações usam `{token}` (chave única).
 * Mensagens Padrão (cliente, via n8n) usam `{{token}}` (chave dupla) — a lista
 * de tokens é a mesma, só muda a sintaxe exibida.
 *
 * As chaves aqui batem com o que o ContextResolver/serviços realmente entregam.
 */
class TemplateVariables
{
    /** contexto => rótulo legível */
    public static function contexts(): array
    {
        return [
            'opportunity' => 'Oportunidade',
            'client'      => 'Cliente',
            'contract'    => 'Contrato',
            'onboarding'  => 'Onboarding',
            'meeting'     => 'Reunião',
            'macro_plan'  => 'Planejamento',
            'task'        => 'Tarefa / Ticket',
            'campaign'    => 'Campanha',
            'portal_lead' => 'Portal (upsell)',
        ];
    }

    /** contexto => [ token => [label, example] ] */
    public static function for(string $context): array
    {
        return self::MAP[$context] ?? [];
    }

    /** Sempre disponível quando há cliente/contato no contexto. */
    public const COMMON = [
        'client_name'  => ['Nome do cliente (apelido ou razão social)', 'Zarpellon'],
        'contact_name' => ['Nome do contato', 'Anderson Vargas'],
    ];

    private const MAP = [
        'opportunity' => [
            'opportunity_title'  => ['Título da oportunidade', 'Zarpellon (Floresta)'],
            'opportunity_type'   => ['Tipo (Novo Cliente / Projeto / Follow-up)', 'Novo Cliente'],
            'opportunity_stage'  => ['Estágio atual', 'Proposta Enviada'],
            'client_name'        => ['Cliente ou nome do contato', 'Zarpellon'],
            'contact_name'       => ['Nome do contato', 'Anderson Vargas'],
            'contact_whatsapp'   => ['WhatsApp do contato', '(49) 99919-9616'],
            'assigned_to_name'   => ['Responsável pela oportunidade', 'Alisson'],
            'proposed_fee'       => ['Fee mensal proposto (R$)', '1.950,00'],
            'proposed_ad_budget' => ['Verba de mídia estimada (R$)', '1.000,00'],
            'contract_months'    => ['Prazo do contrato (meses)', '4'],
            'proposal_url'       => ['Link da proposta', 'https://…'],
            'services_interest'  => ['Serviços de interesse', 'Tráfego Pago, Consultoria'],
        ],
        'client' => [
            'client_name'    => ['Nome do cliente', 'Zarpellon'],
            'client_segment' => ['Segmento', 'Varejo'],
            'contact_name'   => ['Contato principal', 'Anderson Vargas'],
        ],
        'contract' => [
            'client_name'     => ['Nome do cliente', 'Zarpellon'],
            'contract_title'  => ['Título do contrato', 'Contrato — Zarpellon'],
            'contract_fee'    => ['Valor (R$)', '1.950,00'],
            'contract_period' => ['Vigência', '01/09/2026 até 01/03/2027'],
            'contract_status' => ['Status', 'Rascunho'],
        ],
        'onboarding' => [
            'client_name'         => ['Nome do cliente', 'Zarpellon'],
            'onboarding_phase'    => ['Fase atual', 'Fase 2 — Welcome Call'],
            'onboarding_progress' => ['Progresso (%)', '16'],
        ],
        'meeting' => [
            'meeting_title'  => ['Título da reunião', 'Kickoff — Zarpellon'],
            'meeting_type'   => ['Tipo', 'Kickoff Estratégico'],
            'meeting_date'   => ['Data e hora', '05/09/2026 14:00'],
            'client_name'    => ['Cliente', 'Zarpellon'],
            'organizer_name' => ['Organizador', 'Evandro'],
            'attachment_name'=> ['Nome do anexo (quando aplicável)', 'call-2026-09-05.mp3'],
        ],
        'macro_plan' => [
            'macro_plan_title'  => ['Título do planejamento', 'Zarpellon — Planejamento 09/2026'],
            'macro_plan_status' => ['Status', 'Em Planejamento'],
            'client_name'       => ['Cliente', 'Zarpellon'],
            'responsible_name'  => ['Responsável', 'Alisson'],
            'period_end'        => ['Fim do ciclo', '30/11/2026'],
            'meeting_date'      => ['Data da reunião relacionada', '05/09/2026 14:00'],
            'reason'            => ['Motivo da falha (só em "geração falhou")', 'resposta do agente sem "planejamento"'],
        ],
        'task' => [
            'task_title'     => ['Título da tarefa', 'Criar campanha de setembro'],
            'task_status'    => ['Status', 'Em Produção'],
            'task_type'      => ['Tipo', 'Tráfego / Performance'],
            'task_situation' => ['Situação', 'Pronto para produção'],
            'client_name'    => ['Cliente', 'Zarpellon'],
            'executor_name'  => ['Executor', 'Camila'],
            'task_due_date'  => ['Vencimento', '10/09/2026'],
        ],
        'campaign' => [
            'campaign_name'   => ['Nome da campanha', 'ZAR | Conversão | Set'],
            'campaign_status' => ['Status', 'active'],
            'client_name'     => ['Cliente', 'Zarpellon'],
        ],
        'portal_lead' => [
            'client_name'  => ['Nome do cliente', 'Zarpellon'],
            'module_label' => ['Módulo solicitado', 'Central de Leads'],
        ],
    ];
}

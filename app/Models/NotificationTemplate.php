<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplate extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = ['organization_id', 'type', 'channel', 'subject', 'body'];

    // Gatilhos configuráveis. "feedback_campanhas" (ClientContactSubscription)
    // fica de fora de propósito — o cliente já vê isso no portal, não é
    // notificação proativa (decisão do usuário, 2026-07-21).
    public static array $types = [
        'onboarding_boas_vindas' => 'Onboarding — Boas-vindas',
        'portal_acesso_liberado' => 'Acesso ao Portal Liberado',
        'chamado_aberto'         => 'Chamado Aberto',
        'chamado_concluido'      => 'Chamado Concluído',
        'reuniao_lembrete'       => 'Lembrete de Reunião',
        'aprovacao'              => 'Aprovação de Materiais',
        'aprovacao_concluida'    => 'Material Aprovado (pronto pra baixar)',
        'aviso_tarefa'           => 'Aviso ao Cliente (sem entregável)',
        'financeiro'             => 'Financeiro (Notas de Anúncios)',
        'cobranca'               => 'Cobrança / Faturamento',
        'cs_survey'              => 'Pesquisa de Satisfação (CS)',
        'offboarding'            => 'Offboarding',
        'lead_capturado'         => 'Lead Capturado (Central de Leads)',
    ];

    public static array $channels = [
        'whatsapp' => 'WhatsApp',
        'email'    => 'E-mail',
    ];

    // Só documentação na tela por enquanto — substituição de fato das
    // variáveis fica pra quando o disparo automático for implementado.
    public static array $variableHints = [
        'onboarding_boas_vindas' => ['{{cliente}}', '{{contato}}', '{{responsavel_agencia}}'],
        'portal_acesso_liberado' => ['{{cliente}}', '{{contato}}', '{{email}}', '{{senha}}', '{{link_portal}}'],
        'chamado_aberto'         => ['{{cliente}}', '{{contato}}', '{{chamado_titulo}}', '{{link_chamado}}'],
        'chamado_concluido'      => ['{{cliente}}', '{{contato}}', '{{chamado_titulo}}', '{{link_chamado}}'],
        'reuniao_lembrete'       => ['{{cliente}}', '{{contato}}', '{{data_reuniao}}', '{{link_reuniao}}'],
        'aprovacao'              => ['{{cliente}}', '{{contato}}', '{{tarefa}}', '{{link_aprovacao}}'],
        'aprovacao_concluida'    => ['{{cliente}}', '{{contato}}', '{{tarefa}}', '{{link_aprovacao}}'],
        'aviso_tarefa'           => ['{{cliente}}', '{{contato}}', '{{tarefa}}', '{{mensagem}}', '{{link_aprovacao}}'],
        'financeiro'             => ['{{cliente}}', '{{contato}}', '{{valor}}', '{{vencimento}}', '{{link_fatura}}'],
        'cobranca'               => ['{{cliente}}', '{{contato}}', '{{valor}}', '{{vencimento}}', '{{link_fatura}}'],
        'cs_survey'              => ['{{cliente}}', '{{contato}}', '{{link_pesquisa}}'],
        'offboarding'            => ['{{cliente}}', '{{contato}}', '{{data_encerramento}}'],
        'lead_capturado'         => ['{{cliente}}', '{{contato}}', '{{lead_nome}}', '{{lead_telefone}}', '{{canal}}'],
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function typeLabel(): string
    {
        return self::$types[$this->type] ?? $this->type;
    }
}

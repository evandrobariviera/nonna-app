<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientModule extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'module_key',
        'status',
        'enabled_at',
        'enabled_by',
        'requested_at',
        'settings',
    ];

    protected $casts = [
        'settings'     => 'array',
        'enabled_at'   => 'datetime',
        'requested_at' => 'datetime',
    ];

    // Módulos vendáveis — as abas da Central de Performance no Portal.
    public static array $modules = [
        'trafego'       => 'Campanhas de Tráfego',
        'central_leads' => 'Central de Leads',
        'atendimento'   => 'Atendimento',
        'analises_ia'   => 'Análises com IA',
    ];

    // Vitrine do Portal (módulo não contratado): ícone, chamada e 3 benefícios.
    public static array $showcase = [
        'trafego' => [
            'icon'     => 'target',
            'pitch'    => 'Acompanhe as campanhas de anúncios no Meta e no Google: quanto foi investido, quantas pessoas foram alcançadas e quantos resultados cada campanha trouxe, atualizado todos os dias.',
            'benefits' => [
                ['Investimento às claras', 'Quanto foi gasto em cada campanha, dia a dia.'],
                ['Resultado por campanha', 'O que cada anúncio trouxe, lado a lado.'],
                ['Histórico de otimizações', 'O que a equipe ajustou e quando.'],
            ],
        ],
        'central_leads' => [
            'icon'     => 'megaphone',
            'pitch'    => 'Reúna num só lugar os leads que chegam pelo site, Facebook/Instagram e WhatsApp — cada contato vira um cartão num quadro, com histórico de quem falou com quem e em que pé está cada negociação. A IA ainda separa os leads bons dos curiosos.',
            'benefits' => [
                ['Tudo num lugar só', 'Site, Meta e WhatsApp no mesmo quadro.'],
                ['Lead bom em destaque', 'A IA avalia cada contato pelo perfil do seu cliente ideal.'],
                ['Do anúncio à venda', 'Veja quanto custou cada venda e quanto voltou do investimento.'],
            ],
        ],
        'atendimento' => [
            'icon'     => 'headphones',
            'pitch'    => 'A IA lê as conversas de WhatsApp da sua equipe e mostra como está o atendimento: tempo de resposta, onde as vendas se perdem e o que fazer para vender mais.',
            'benefits' => [
                ['Nota do atendimento', 'Um número simples para acompanhar a evolução.'],
                ['Onde as vendas escapam', 'Os pontos da conversa em que o cliente desiste.'],
                ['Recomendações práticas', 'Ajustes rápidos e mudanças maiores, em ordem de prioridade.'],
            ],
        ],
        'analises_ia' => [
            'icon'     => 'sparkles',
            'pitch'    => 'A cada ciclo de otimização, a IA analisa suas campanhas até a venda e explica, em linguagem simples, como elas estão indo e o que a equipe vai fazer.',
            'benefits' => [
                ['Como está indo', 'Um resumo claro, sem jargão de marketing.'],
                ['Alertas na hora certa', 'Custo subindo, verba parada, anúncio cansado.'],
                ['Foco no que vende', 'Análise pelo lead bom e pela venda, não só por clique.'],
            ],
        ],
    ];

    public static array $statuses = [
        'nao_contratado' => ['label' => 'Não Contratado', 'color' => 'muted'],
        'ativo'          => ['label' => 'Ativo',          'color' => 'green'],
    ];

    public function moduleLabel(): string
    {
        return self::$modules[$this->module_key] ?? $this->module_key;
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'ativo';
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    // Central de Leads: "lead atribuído some para os outros" — com a opção ligada,
    // cada pessoa do cliente no Portal vê só os seus leads e os sem responsável;
    // o contato principal e o time da Nonna continuam vendo todos.
    public static function leadsHideAssigned(string $clientId): bool
    {
        return (bool) self::where('client_id', $clientId)->where('module_key', 'central_leads')
            ->first()?->setting('hide_assigned', false);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }
}

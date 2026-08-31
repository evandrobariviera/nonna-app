<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Opportunity extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'client_id',
        'title',
        'type',
        'stage',
        'services_interest',
        'proposed_fee',
        'proposed_ad_budget',
        'notes',
        'proposal_url',
        'contract_months',
        'lost_reason',
        'won_at',
        'lost_at',
        'assigned_to',
        'created_by',
    ];

    protected $casts = [
        'services_interest'  => 'array',
        'proposed_fee'       => 'decimal:2',
        'proposed_ad_budget' => 'decimal:2',
        'won_at'             => 'datetime',
        'lost_at'            => 'datetime',
        'contract_months'    => 'integer',
    ];

    // Tipo da oportunidade — muda o fluxo de fechamento e a mensagem que a
    // equipe manda no grupo da agência (ver Automações "Oportunidade Ganha").
    public static array $types = [
        'novo_cliente' => 'Novo Cliente',
        'projeto'      => 'Projeto (cliente atual)',
        'follow_up'    => 'Follow-up',
    ];

    public static array $stages = [
        'novo_lead'          => ['label' => 'Novo Lead',          'color' => 'muted'],
        'qualificacao'       => ['label' => 'Qualificação',       'color' => 'purple'],
        'diagnostico_reuniao'=> ['label' => 'Diagnóstico / Reunião', 'color' => 'purple'],
        'proposta_enviada'   => ['label' => 'Proposta Enviada',   'color' => 'orange'],
        'negociando'         => ['label' => 'Em Negociação',      'color' => 'orange'],
        'ganho'              => ['label' => 'Ganho',              'color' => 'green'],
        'perdido'            => ['label' => 'Perdido',            'color' => 'red'],
    ];

    public function stageLabel(): string
    {
        return self::$stages[$this->stage]['label'] ?? $this->stage;
    }

    public function typeLabel(): string
    {
        $type = $this->type ?? 'novo_cliente';
        return self::$types[$type] ?? $type;
    }

    // Só "novo_cliente" cria um Client no fechamento; os outros vinculam a um
    // cliente já existente.
    public function createsClient(): bool
    {
        return ($this->type ?? 'novo_cliente') === 'novo_cliente';
    }

    public function stageColor(): string
    {
        return self::$stages[$this->stage]['color'] ?? 'muted';
    }

    public function isOpen(): bool
    {
        return !in_array($this->stage, ['ganho', 'perdido']);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

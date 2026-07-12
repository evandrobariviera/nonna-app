<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContactSubscription extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['client_contact_id', 'type', 'channels'];

    protected $casts = [
        'channels' => 'array',
    ];

    // Tipos de comunicação que um contato do cliente pode receber. Só
    // "aprovacao" tem lógica de envio real hoje (TaskApprovalService) — os
    // demais ficam com o cadastro pronto pra quando essas funcionalidades
    // existirem.
    public static array $types = [
        'aprovacao'           => 'Aprovação de Materiais',
        'financeiro'          => 'Financeiro (Notas de Anúncios)',
        'feedback_campanhas'  => 'Feedback de Campanhas',
        'cobranca'            => 'Cobrança / Faturamento',
    ];

    public static array $channels = [
        'whatsapp' => 'WhatsApp',
        'email'    => 'E-mail',
    ];

    public function clientContact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class);
    }

    public function typeLabel(): string
    {
        return self::$types[$this->type] ?? $this->type;
    }

    public function channelLabels(): array
    {
        return array_map(fn ($c) => self::$channels[$c] ?? $c, $this->channels ?? []);
    }
}

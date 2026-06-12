<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ClientContact extends Pivot
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'client_contacts';

    public $incrementing = false;

    protected $keyType = 'string';

    // Papéis possíveis de um contato dentro de um cliente
    const ROLES = [
        'decisor'          => 'Decisor',
        'gestor_marketing' => 'Gestor de Marketing',
        'acompanhante'     => 'Acompanhante',
        'financeiro'       => 'Financeiro',
        'tecnico'          => 'Técnico',
    ];

    // Papéis que recebem links de aprovação por padrão
    const APPROVAL_ROLES = ['decisor', 'gestor_marketing'];

    protected $fillable = [
        'client_id',
        'contact_id',
        'role',
        'is_primary',
        'receives_approvals',
    ];

    protected $casts = [
        'is_primary'         => 'boolean',
        'receives_approvals' => 'boolean',
    ];

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ($this->role ?? '—');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}

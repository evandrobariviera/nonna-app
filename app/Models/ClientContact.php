<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ClientContact extends Pivot
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'client_contacts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'client_id',
        'contact_id',
        'role',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function roleLabel(): string
    {
        return \App\Http\Controllers\ClientContactController::$roles[$this->role] ?? ($this->role ?? '—');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function subscriptions(): HasMany
    {
        // FK explícita: Pivot sobrescreve getForeignKey() pra outro fim (chave
        // do pivot na relação belongsToMany), então o hasMany() não consegue
        // adivinhar a FK sozinho como adivinharia numa Model comum.
        return $this->hasMany(ClientContactSubscription::class, 'client_contact_id');
    }
}

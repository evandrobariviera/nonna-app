<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Contact extends Authenticatable
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $hidden = ['password', 'remember_token'];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'whatsapp',
        'job_title',
        'company_name',
        'source',
        'status',
        'notes',
        'assigned_to',  // FK users (bigint)
        'created_by',   // FK users (bigint)
        'password',
        'portal_access_enabled',
    ];

    protected function casts(): array
    {
        return [
            'portal_access_enabled' => 'boolean',
            'portal_last_login_at'  => 'datetime',
            'password'              => 'hashed',
        ];
    }

    public static array $sources = [
        'whatsapp'   => 'WhatsApp',
        'instagram'  => 'Instagram',
        'indicacao'  => 'Indicação',
        'site'       => 'Site',
        'linkedin'   => 'LinkedIn',
        'evento'     => 'Evento',
        'outros'     => 'Outros',
    ];

    public static array $statuses = [
        'ativo'   => ['label' => 'Ativo',   'color' => 'green'],
        'inativo' => ['label' => 'Inativo', 'color' => 'muted'],
    ];

    // E-mail é o identificador de login do portal — normaliza pra evitar
    // mismatch de maiúscula/minúscula entre cadastro e tentativa de login,
    // e pra bater com o índice único parcial (lower(email)).
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value ? Str::lower(trim($value)) : null;
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_contacts', 'contact_id', 'client_id')
            ->using(ClientContact::class)
            ->withPivot(['role', 'is_primary'])
            ->withTimestamps();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

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
    ];

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
            ->withPivot(['role', 'is_primary'])
            ->withTimestamps();
    }
}

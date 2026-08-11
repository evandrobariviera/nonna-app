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
        'organization_id',
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
    ];

    protected function casts(): array
    {
        return [
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
            ->withPivot(['role', 'is_primary', 'portal_access_enabled'])
            ->withTimestamps();
    }

    // Tem o Portal liberado em pelo menos um cliente — não confundir com "tem
    // senha definida" (isso só diz que já fez algum acesso alguma vez, mesmo
    // que hoje esteja revogado em todo mundo).
    public function hasAnyPortalAccess(): bool
    {
        return $this->clients()->wherePivot('portal_access_enabled', true)->exists();
    }

    // Delete de verdade só é permitido quando não sobra NENHUMA associação —
    // devolve um mapa label => contagem só com o que estiver bloqueando
    // (array vazio = seguro apagar). Usado por ContactController::destroy().
    public function blockingAssociations(): array
    {
        $checks = [
            'clientes vinculados'         => $this->clients()->count(),
            'oportunidades'               => $this->opportunities()->count(),
            'tarefas criadas pelo portal' => Task::where('contact_id', $this->id)->count(),
            'comentários no portal'       => TaskComment::where('contact_id', $this->id)->count(),
        ];

        if ($this->hasAnyPortalAccess()) {
            $checks['acesso ao portal ativo'] = 1;
        }

        return array_filter($checks, fn ($count) => $count > 0);
    }
}

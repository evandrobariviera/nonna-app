<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Organization extends Model
{
    use HasUuids, HasApiTokens;

    protected $connection = 'pgsql';

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'status',
        'owner_user_id',
        'trial_ends_at',
        'settings',
    ];

    protected $casts = [
        'settings'       => 'array',
        'trial_ends_at'  => 'datetime',
    ];

    public static array $plans = [
        'trial'      => 'Trial',
        'starter'    => 'Starter',
        'pro'        => 'Pro',
        'enterprise' => 'Enterprise',
    ];

    public static array $statuses = [
        'trial'     => 'Trial',
        'active'    => 'Ativo',
        'suspended' => 'Suspenso',
        'cancelled' => 'Cancelado',
    ];

    // ── Branding helpers ──

    public function brandColor(string $key = 'primary_color', string $default = '#6A5ACD'): string
    {
        return data_get($this->settings, "branding.{$key}", $default);
    }

    public function logoUrl(): ?string
    {
        return data_get($this->settings, 'branding.logo_url');
    }

    // ── Relacionamentos ──

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users', 'organization_id', 'user_id')
            ->using(OrganizationUser::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(OrganizationIntegration::class);
    }

    public function integration(string $provider): ?OrganizationIntegration
    {
        return $this->integrations()->where('provider', $provider)->where('status', 'connected')->first();
    }
}

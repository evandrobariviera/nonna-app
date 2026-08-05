<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'is_super_admin', 'client_id', 'avatar_path', 'avatar_disk'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public function avatarUrl(): ?string
    {
        if (!$this->avatar_path) {
            return null;
        }

        if ($this->avatar_disk === 'r2') {
            return Storage::disk('r2')->temporaryUrl($this->avatar_path, now()->addHours(24));
        }

        return Storage::disk($this->avatar_disk)->url($this->avatar_path);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_super_admin'    => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function isClient(): bool
    {
        return $this->client_id !== null;
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_users', 'user_id', 'organization_id')
            ->using(OrganizationUser::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function functionalRoles(): BelongsToMany
    {
        return $this->belongsToMany(FunctionalRole::class, 'functional_role_user')->withTimestamps();
    }

    public function currentOrganization(): ?Organization
    {
        return app()->has('currentOrganization') ? app('currentOrganization') : null;
    }
}

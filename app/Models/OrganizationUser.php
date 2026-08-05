<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganizationUser extends Pivot
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'organization_users';

    public $incrementing = false;

    protected $fillable = ['organization_id', 'user_id', 'role'];

    public static array $roles = [
        'owner'   => 'Proprietário',
        'admin'   => 'Administrador',
        'manager' => 'Gestor',
        'member'  => 'Membro',
    ];
}

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

    protected $fillable = ['organization_id', 'user_id', 'role', 'function_roles'];

    protected $casts = [
        'function_roles' => 'array',
    ];

    public static array $roles = [
        'owner'   => 'Proprietário',
        'admin'   => 'Administrador',
        'manager' => 'Gestor',
        'member'  => 'Membro',
    ];

    public static array $functionRoles = [
        'direcao_geral'    => 'Direção Geral',
        'direcao_criativa' => 'Direção Criativa',
        'coo'              => 'COO & Operação',
        'gestor_campanhas' => 'Gestor de Campanhas',
        'head_criativa'    => 'Head Criativa & Copy',
        'head_tech'        => 'Head de Tecnologia',
        'designer'         => 'Design',
        'trafego'          => 'Tráfego',
        'dev'              => 'Dev',
    ];
}

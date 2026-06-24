<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class VisionController extends Controller
{
    private array $roleMap = [
        'direcao_geral'    => 'direcao-geral',
        'direcao_criativa' => 'direcao-criativa',
        'coo'              => 'coo',
        'gestor_campanhas' => 'gestor-campanhas',
        'head_criativa'    => 'head-criativa',
        'head_tech'        => 'head-tech',
        'designer'         => 'designer',
        'trafego'          => 'trafego',
        'dev'              => 'dev',
    ];

    public function show(Request $request, string $role): View
    {
        $orgRole       = app('currentOrgRole');
        $isOrgAdmin    = in_array($orgRole, ['owner', 'admin']);
        $functionRoles = app('userFunctionRoles') ?? [];

        abort_unless($isOrgAdmin || in_array($role, $functionRoles), 403);

        return view('visoes.' . ($this->roleMap[$role] ?? $role), compact('role'));
    }
}

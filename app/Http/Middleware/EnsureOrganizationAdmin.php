<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAdmin
{
    // Sem parâmetro, continua só owner/admin (Configurações). Rota que também deve
    // liberar Gestor usa 'org.admin:owner,admin,manager' (ver rota do Monitor de
    // Trabalho em routes/web.php).
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!app()->has('currentOrganization')) {
            abort(403, 'Nenhuma organização ativa.');
        }

        $role = app()->has('currentOrgRole') ? app('currentOrgRole') : null;
        $allowedRoles = $roles ?: ['owner', 'admin'];

        if (!in_array($role, $allowedRoles)) {
            abort(403, 'Acesso restrito a administradores da organização.');
        }

        return $next($request);
    }
}

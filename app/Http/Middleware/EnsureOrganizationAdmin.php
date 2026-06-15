<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!app()->has('currentOrganization')) {
            abort(403, 'Nenhuma organização ativa.');
        }

        $role = app()->has('currentOrgRole') ? app('currentOrgRole') : null;

        if (!in_array($role, ['owner', 'admin'])) {
            abort(403, 'Acesso restrito a administradores da organização.');
        }

        return $next($request);
    }
}

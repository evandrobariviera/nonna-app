<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host     = $request->getHost();
        $domain   = config('app.domain');
        $mainHost = parse_url(config('app.url'), PHP_URL_HOST);
        $organization = null;

        if ($domain && str_ends_with($host, '.' . $domain) && $host !== $mainHost) {
            $slug = substr($host, 0, -strlen('.' . $domain));
            $organization = Organization::where('slug', $slug)
                ->whereIn('status', ['active', 'trial'])
                ->first();

            if (!$organization) {
                throw new NotFoundHttpException("Organização não encontrada.");
            }
        } elseif (auth()->check()) {
            // Fallback para acesso via domínio principal (dev ou admin)
            $organization = auth()->user()->organizations()
                ->whereIn('organizations.status', ['active', 'trial'])
                ->first();
        }

        if ($organization) {
            app()->instance('currentOrganization', $organization);

            $role          = null;
            $functionRoles = [];

            if (auth()->check()) {
                $pivot = $organization->users()
                    ->where('user_id', auth()->id())
                    ->first()?->pivot;

                $role          = $pivot?->role;
                $functionRoles = $pivot?->function_roles ?? [];
            }

            app()->instance('currentOrgRole', $role);
            app()->instance('userFunctionRoles', $functionRoles);

            view()->share('currentOrg', $organization);
            view()->share('currentOrgRole', $role);
            view()->share('isOrgAdmin', in_array($role, ['owner', 'admin']));
            view()->share('userFunctionRoles', $functionRoles);
        } else {
            view()->share('currentOrg', null);
            view()->share('currentOrgRole', null);
            view()->share('isOrgAdmin', false);
            view()->share('userFunctionRoles', []);
        }

        return $next($request);
    }
}

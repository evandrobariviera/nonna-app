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
        $host   = $request->getHost();
        $domain = config('app.domain');
        $organization = null;

        if ($domain && str_ends_with($host, '.' . $domain)) {
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

            $role = null;
            if (auth()->check()) {
                $role = $organization->users()
                    ->where('user_id', auth()->id())
                    ->first()?->pivot?->role;
            }

            app()->instance('currentOrgRole', $role);

            view()->share('currentOrg', $organization);
            view()->share('currentOrgRole', $role);
            view()->share('isOrgAdmin', in_array($role, ['owner', 'admin']));
        } else {
            view()->share('currentOrg', null);
            view()->share('currentOrgRole', null);
            view()->share('isOrgAdmin', false);
        }

        return $next($request);
    }
}

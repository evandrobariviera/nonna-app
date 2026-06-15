<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // O token Sanctum é vinculado à Organization (não ao User)
        // auth('sanctum')->user() retorna a Organization autenticada
        $organization = auth('sanctum')->user();

        if (!$organization) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        app()->instance('currentOrganization', $organization);

        return $next($request);
    }
}

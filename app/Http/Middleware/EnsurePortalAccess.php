<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalAccess
{
    /**
     * Handle an incoming request.
     *
     * Checa o guard "portal" manualmente — nunca usar o middleware nativo
     * `auth:portal`, que chama Auth::shouldUse() e trocaria o guard default
     * da request pro resto da execução. Como `sessions.user_id` é bigint
     * (FK pra `users`), gravar a sessão no fim de uma request autenticada
     * como Contact (uuid) quebraria com erro de tipo no Postgres.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('portal')->check()) {
            return redirect()->guest(route('portal.login'));
        }

        return $next($request);
    }
}

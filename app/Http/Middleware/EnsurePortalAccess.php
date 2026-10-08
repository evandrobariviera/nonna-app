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

        // Entrou pelo link de aprovação (login mágico): só a Central. Qualquer
        // outra tela do Portal pede senha — sai da sessão mágica e manda pro login,
        // que devolve pra tela pedida depois de entrar.
        $magic = app(\App\Services\PortalMagicAccess::class);
        if ($magic->active() && !$request->routeIs(...\App\Services\PortalMagicAccess::ALLOWED_ROUTES)) {
            Auth::guard('portal')->logout();
            $magic->forget($request);

            return redirect()->guest(route('portal.login'))
                ->with('status', 'Para ver o restante do Portal, entre com seu e-mail e senha.');
        }

        return $next($request);
    }
}

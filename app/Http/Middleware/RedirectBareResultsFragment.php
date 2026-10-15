<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Os endpoints "/resultados" das listagens devolvem só o fragmento HTML (sem layout) pro
 * live-filter.js trocar via fetch. Aberto direto no navegador (link de paginação em aba
 * nova, URL salva/compartilhada) virava uma página crua, sem CSS. Requisição que não é
 * AJAX → redireciona pra tela completa, mantendo os filtros e a página.
 *
 * /clientes/resultados?page=2        → /clientes?page=2
 * /sprints/{id}/lista/resultados     → /sprints/{id}
 */
class RedirectBareResultsFragment
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->ajax() && str_ends_with($request->path(), '/resultados')) {
            $path = preg_replace('#(/lista)?/resultados$#', '', $request->path());
            $query = $request->getQueryString();

            return redirect('/' . ltrim($path, '/') . ($query ? '?' . $query : ''));
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * `throttle:X,Y` com contador por rota.
 *
 * O ThrottleRequests do Laravel usa só o id do usuário como chave, então TODAS as rotas
 * com `throttle:X,Y` dividiam o mesmo contador: o salvamento automático dos campos do
 * documento (240/min) estourava o limite do "Montar rascunho" (6/min) e devolvia 429.
 * Aqui a chave inclui a rota (nome ou método+URI), cada limite conta só os seus acessos.
 */
class ThrottlePerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        $routeKey = $route
            ? ($route->getName() ?: implode('|', $route->methods()).'|'.$route->uri())
            : '';

        return parent::resolveRequestSignature($request).'|'.$routeKey;
    }
}

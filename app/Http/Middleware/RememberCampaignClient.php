<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Central de Campanhas: o cliente escolhido no filtro segue junto de uma aba
 * para a outra (e ao voltar pelo menu). Fica na sessão de cada pessoa.
 *
 * - ?client_id=<uuid> ou ?client_id=meus → guarda;
 * - ?client_id=todos (ou vazio)          → esquece e mostra todos;
 * - sem client_id na URL                  → usa o que está guardado.
 *
 * "meus" (clientes em que sou responsável) só existe em algumas abas; nas
 * outras ele não é aplicado (mostra todos), mas continua guardado.
 */
class RememberCampaignClient
{
    public const KEY = 'campaign_central.client_id';

    private const SUPPORTS_MINE = ['campaign-central.today', 'campaign-central.optimizations', 'ad-connections.index'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query->has('client_id')) {
            $value = (string) $request->query('client_id');
            if ($value === '' || $value === 'todos') {
                $request->session()->forget(self::KEY);
                $request->query->remove('client_id');
            } elseif ($value === 'meus' || Str::isUuid($value)) {
                $request->session()->put(self::KEY, $value);
            }
        } elseif ($saved = $request->session()->get(self::KEY)) {
            if ($saved !== 'meus' || $request->routeIs(...self::SUPPORTS_MINE)) {
                $request->query->set('client_id', $saved);
            }
        }

        return $next($request);
    }

    /** Cliente guardado (uuid), para links fora da Central. */
    public static function current(): ?string
    {
        $saved = session(self::KEY);

        return $saved && $saved !== 'meus' ? $saved : null;
    }
}

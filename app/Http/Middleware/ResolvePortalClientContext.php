<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolvePortalClientContext
{
    /**
     * Resolve qual Cliente está "ativo" na sessão do portal — um Contato
     * pode estar ligado a vários Clientes (client_contacts), então precisa
     * de um "atual" pra escopar os dados, no mesmo espírito do
     * app('currentOrganization') que já existe pro time interno (SetTenant).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $contact = Auth::guard('portal')->user();
        $clients = $contact->clients()->get();

        if ($clients->isEmpty()) {
            Auth::guard('portal')->logout();
            abort(403, 'Este contato não está vinculado a nenhum cliente.');
        }

        $clientId = $request->session()->get('portal_current_client_id');
        $current = $clients->firstWhere('id', $clientId)
            ?? $clients->first(fn ($c) => (bool) $c->pivot->is_primary)
            ?? $clients->first();

        $request->session()->put('portal_current_client_id', $current->id);

        app()->instance('currentPortalClient', $current);

        view()->share('currentClient', $current);
        view()->share('portalContact', $contact);
        view()->share('portalClients', $clients);

        return $next($request);
    }
}

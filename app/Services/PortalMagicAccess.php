<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Contact;
use App\Models\TaskApprovalToken;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * "Login mágico" do link de aprovação (WhatsApp/e-mail): abrir o link entra
 * no Portal como aquele contato, sem senha, mas SÓ na Central de Aprovações
 * dos clientes em que ele é aprovador. O resto do Portal pede senha
 * (EnsurePortalAccess). Não exige portal_access_enabled — quem aprova pelo
 * WhatsApp normalmente nunca teve acesso ao Portal.
 *
 * Desligar o acesso = tirar a assinatura "aprovacao" do contato no cliente
 * (aba Contatos): a checagem é refeita a cada request.
 */
class PortalMagicAccess
{
    public const FLAG    = 'portal_magic';
    public const CLIENTS = 'portal_magic_client_ids';

    // Rotas do Portal liberadas na sessão mágica. decide fica de fora de
    // propósito: pelo link a decisão é do token do contato (unanimidade).
    public const ALLOWED_ROUTES = [
        'portal.approvals.index',
        'portal.approvals.project',
        'portal.approvals.loose',
        'portal.approvals.show',
        'portal.approvals.review',
        'portal.approvals.review-exit',
        'portal.approvals.approve-all',
        'portal.client-context.switch',
        'portal.logout',
    ];

    public function enterFromToken(Request $request, TaskApprovalToken $token): void
    {
        $contact  = $token->contact;
        $clientId = $token->round->task->client_id;

        if (!$contact || !$clientId || !$this->canApprove($contact->id, $clientId)) {
            return;
        }

        $guard   = Auth::guard('portal');
        $current = $guard->user();

        // Já está logado com senha como esse mesmo contato — não rebaixa a sessão.
        if ($current && $current->id === $contact->id && !$this->active()) {
            return;
        }

        if (!$current || $current->id !== $contact->id) {
            $guard->login($contact);
            $request->session()->regenerate();
            $request->session()->forget(self::CLIENTS);
        }

        $ids = collect($request->session()->get(self::CLIENTS, []))->push($clientId)->unique()->values()->all();

        $request->session()->put(self::FLAG, true);
        $request->session()->put(self::CLIENTS, $ids);
        $request->session()->put('portal_current_client_id', $clientId);
    }

    public function active(): bool
    {
        return (bool) session(self::FLAG, false);
    }

    /**
     * Clientes que a sessão mágica ainda pode ver — com o mesmo shape (pivot)
     * que ResolvePortalClientContext usa pra sessão com senha.
     *
     * @return Collection<int, Client>
     */
    public function allowedClients(Contact $contact): Collection
    {
        $ids = session(self::CLIENTS, []);

        return $contact->clients()
            ->whereIn('clients.id', $ids)
            ->get()
            ->filter(fn (Client $c) => $this->canApprove($contact->id, $c->id))
            ->values();
    }

    public function forget(Request $request): void
    {
        $request->session()->forget([self::FLAG, self::CLIENTS]);
    }

    private function canApprove(string $contactId, string $clientId): bool
    {
        return ClientContact::where('contact_id', $contactId)
            ->where('client_id', $clientId)
            ->whereHas('subscriptions', fn ($q) => $q->where('type', 'aprovacao'))
            ->exists();
    }
}

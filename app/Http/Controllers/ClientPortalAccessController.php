<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Contact;
use App\Models\NotificationTemplate;
use App\Services\NotificationDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientPortalAccessController extends Controller
{
    public function __construct(private NotificationDispatchService $notifications)
    {
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);

        $data = $request->validate([
            'contact_id' => [
                'required', 'uuid',
                Rule::exists('client_contacts', 'contact_id')->where('client_id', $client->id),
            ],
            // Só obrigatório no primeiro acesso do contato (ver checagem abaixo) — se
            // ele já loga em outro cliente, conceder acesso aqui é só ligar o vínculo,
            // não precisa (nem deve) resetar a senha que ele já usa.
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $contact = Contact::findOrFail($data['contact_id']);

        if (!$contact->email) {
            return back()->withErrors(['contact_id' => 'Este contato não tem e-mail cadastrado — adicione um e-mail antes de habilitar o acesso.']);
        }

        if (!$contact->password && !$data['password']) {
            return back()->withErrors(['password' => 'Este contato ainda não tem senha de acesso — defina uma para o primeiro acesso ao Portal.']);
        }

        // Duas Contact diferentes com o mesmo e-mail login-capable (senha definida)
        // quebrariam o login (auth resolve por e-mail) — provavelmente é a mesma
        // pessoa cadastrada duas vezes; o certo é vincular o Contato já existente
        // a este cliente em vez de habilitar um segundo cadastro duplicado.
        $clash = Contact::where('organization_id', $contact->organization_id)
            ->where('id', '!=', $contact->id)
            ->whereRaw('lower(email) = ?', [Str::lower($contact->email)])
            ->whereNotNull('password')
            ->exists();

        if ($clash) {
            return back()->withErrors(['contact_id' => 'Já existe outro contato cadastrado com este e-mail e acesso ao Portal — provavelmente a mesma pessoa duplicada. Vincule o contato já existente a este cliente em vez de habilitar este.']);
        }

        if ($data['password']) {
            $contact->update(['password' => $data['password']]);
        }

        ClientContact::where('client_id', $client->id)
            ->where('contact_id', $contact->id)
            ->update(['portal_access_enabled' => true]);

        // Avisa o contato com e-mail/senha de acesso — dispara direto (não via
        // send()/assinatura, igual TaskApprovalService) porque é o próprio ato de
        // habilitar o acesso que decide o destinatário, não uma assinatura prévia.
        // Silencioso se o template "Acesso ao Portal Liberado" não estiver preenchido
        // pra nenhum canal, ou se N8N_NOTIFICATION_WEBHOOK_URL não estiver configurado.
        foreach (array_keys(NotificationTemplate::$channels) as $channel) {
            $this->notifications->dispatch('portal_acesso_liberado', $channel, $client, $contact, [
                'email'       => $contact->email,
                'senha'       => $data['password'],
                'link_portal' => route('portal.login'),
            ]);
        }

        return back()->with('success', 'Acesso ao portal habilitado.');
    }

    public function destroy(Client $client, Contact $portalContact): RedirectResponse
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);
        abort_unless($client->contacts()->where('contact_id', $portalContact->id)->exists(), 403);

        // Revoga só o vínculo com ESTE cliente — se o contato atende outras
        // empresas, o acesso delas continua intacto.
        ClientContact::where('client_id', $client->id)
            ->where('contact_id', $portalContact->id)
            ->update(['portal_access_enabled' => false]);

        return back()->with('success', 'Acesso removido.');
    }
}

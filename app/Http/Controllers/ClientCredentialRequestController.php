<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientCredentialRequest;
use App\Models\Contact;
use App\Models\NotificationTemplate;
use App\Services\NotificationDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientCredentialRequestController extends Controller
{
    public function __construct(private NotificationDispatchService $notifications)
    {
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);

        $data = $request->validate([
            'contact_ids'   => ['required', 'array', 'min:1'],
            'contact_ids.*' => [
                'uuid',
                Rule::exists('client_contacts', 'contact_id')->where('client_id', $client->id),
            ],
        ]);

        foreach ($data['contact_ids'] as $contactId) {
            $contact = Contact::findOrFail($contactId);

            $creqRequest = ClientCredentialRequest::create([
                'client_id'    => $client->id,
                'contact_id'   => $contact->id,
                'token'        => (string) Str::uuid(),
                'requested_by' => Auth::id(),
                'expires_at'   => now()->addDays(15),
            ]);

            foreach (array_keys(NotificationTemplate::$channels) as $channel) {
                $this->notifications->dispatch('credenciais_solicitadas', $channel, $client, $contact, [
                    'link_credenciais' => route('credential-request.show', $creqRequest->token),
                ]);
            }
        }

        return back()->with('success', 'Link de solicitação enviado.');
    }
}

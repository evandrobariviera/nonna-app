<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contact;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Services\NotificationDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Reenviar ao cliente" no card Link de Cadastro: manda de novo o link do cadastro
 * inicial (/cadastro/{token}) pros contatos escolhidos, por WhatsApp e e-mail
 * (Mensagem Padrão `cadastro_reenvio`). Se o cadastro já foi concluído, reabre —
 * o formulário vem preenchido com os dados atuais e o cliente só corrige o que
 * precisar. Reenviar de novo não recria contrato/ticket: a 2ª etapa do onboarding
 * é idempotente (ClientOnboardingService::onRegistrationComplete).
 */
class ClientRegistrationResendController extends Controller
{
    public function __construct(private NotificationDispatchService $notifications)
    {
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        abort_if($client->organization_id !== app('currentOrganization')->id, 403);

        // Bag próprio: a aba Senhas também tem um campo contact_ids na mesma página.
        $data = $request->validateWithBag('registrationResend', [
            'contact_ids'   => ['required', 'array', 'min:1'],
            'contact_ids.*' => [
                'uuid',
                Rule::exists('client_contacts', 'contact_id')->where('client_id', $client->id),
            ],
        ], [
            'contact_ids.required' => 'Escolha pelo menos um contato.',
        ]);

        // Sem link ainda (cliente cadastrado por fora do funil) → gera agora.
        if (!$client->registration_token) {
            $client->generateRegistrationToken();
        }

        $reopened = false;
        if ($client->isRegistrationComplete()) {
            $client->update(['registration_completed_at' => null]);
            $reopened = true;
        }

        $link = route('clients.register', $client->registration_token);
        $startedAt = now()->subSecond();

        foreach ($data['contact_ids'] as $contactId) {
            $contact = Contact::findOrFail($contactId);
            foreach (array_keys(NotificationTemplate::$channels) as $channel) {
                $this->notifications->dispatch('cadastro_reenvio', $channel, $client, $contact, [
                    'link_cadastro' => $link,
                ], related: $client);
            }
        }

        $attempts = $this->attemptsSince($client, $startedAt);
        $sent = $attempts->where('status', 'sent');

        if ($sent->isNotEmpty()) {
            $client->onboarding?->markDone('link_cadastro_enviado');
        }

        return back()->with('registration_resend', [
            'ok'   => $sent->isNotEmpty(),
            'text' => $this->summary($sent, $attempts, $reopened),
        ]);
    }

    private function attemptsSince(Client $client, $since)
    {
        return NotificationDispatch::where('client_id', $client->id)
            ->where('type', 'cadastro_reenvio')
            ->where('created_at', '>=', $since)
            ->get();
    }

    private function summary($sent, $attempts, bool $reopened): string
    {
        $channels = NotificationTemplate::$channels;
        $parts = [];

        if ($sent->isNotEmpty()) {
            $parts[] = 'Link de cadastro reenviado: ' . $sent
                ->map(fn ($a) => "{$a->contact_name} (" . ($channels[$a->channel] ?? $a->channel) . ')')
                ->implode(', ') . '.';
        } else {
            $parts[] = 'Nada foi enviado.';
        }

        foreach ($attempts->where('status', '!=', 'sent') as $a) {
            $parts[] = "{$a->contact_name} por " . ($channels[$a->channel] ?? $a->channel) . ": {$a->reason}";
        }

        if ($reopened) {
            $parts[] = 'O cadastro foi reaberto pro cliente revisar os dados.';
        }

        return implode(' ', $parts);
    }
}

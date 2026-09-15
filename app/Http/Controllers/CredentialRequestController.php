<?php

namespace App\Http\Controllers;

use App\Models\ClientCredential;
use App\Models\ClientCredentialRequest;
use App\Services\SystemNotificationService;
use Illuminate\Http\Request;

class CredentialRequestController extends Controller
{
    public function show(string $token)
    {
        $credentialRequest = ClientCredentialRequest::where('token', $token)
            ->with(['client', 'contact'])
            ->firstOrFail();

        if (! $credentialRequest->isValid()) {
            return view('credential-request.expired', compact('credentialRequest'));
        }

        $platforms = ClientCredential::$platforms;

        return view('credential-request.show', compact('credentialRequest', 'platforms'));
    }

    public function submit(Request $request, string $token)
    {
        $credentialRequest = ClientCredentialRequest::where('token', $token)
            ->with(['client', 'contact'])
            ->firstOrFail();

        if (! $credentialRequest->isValid()) {
            return redirect()->route('credential-request.show', $token);
        }

        $data = $request->validate([
            'entries'                   => ['required', 'array', 'min:1'],
            'entries.*.platform'        => ['nullable', 'string', 'max:50'],
            'entries.*.platform_custom' => ['nullable', 'string', 'max:100'],
            'entries.*.access_url'      => ['nullable', 'url', 'max:500'],
            'entries.*.username'        => ['nullable', 'string', 'max:255'],
            'entries.*.password'        => ['nullable', 'string', 'max:500'],
            'entries.*.notes'           => ['nullable', 'string'],
        ]);

        // Só considera linhas onde o cliente realmente escolheu uma plataforma —
        // o formulário sempre manda todas as linhas do form, mesmo as vazias
        // que ele não chegou a preencher.
        $entries = collect($data['entries'])->filter(fn ($e) => filled($e['platform'] ?? null))->values();

        if ($entries->isEmpty()) {
            return back()->withErrors(['entries' => 'Preencha ao menos uma plataforma antes de enviar.'])->withInput();
        }

        foreach ($entries as $entry) {
            ClientCredential::create([
                'client_id'              => $credentialRequest->client_id,
                'credential_request_id'  => $credentialRequest->id,
                'platform'               => $entry['platform'],
                'platform_custom'        => $entry['platform_custom'] ?? null,
                'access_url'             => $entry['access_url'] ?? null,
                'username'               => $entry['username'] ?? null,
                'password'               => $entry['password'] ?? null,
                'notes'                  => $entry['notes'] ?? null,
                'created_by'             => $credentialRequest->requested_by,
            ]);
        }

        $credentialRequest->registerSubmission($entries->count());

        $credentialRequest->client->onboarding?->markDone('acessos_coletados');

        if ($credentialRequest->requested_by) {
            app(SystemNotificationService::class)->send(
                'credentials.recebidas',
                collect([$credentialRequest->requestedBy]),
                [
                    'client_name'  => $credentialRequest->client->displayName(),
                    'contact_name' => $credentialRequest->contact->name,
                ],
                route('clients.show', [$credentialRequest->client, 'tab' => 'senhas']),
                $credentialRequest,
                $credentialRequest->client->organization_id,
            );
        }

        return view('credential-request.thanks', compact('credentialRequest'));
    }
}

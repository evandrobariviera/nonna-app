<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientLeadSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientLeadSourceController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $this->validated($request);

        $client->leadSources()->create($data);

        return redirect()->route('clients.show', [$client, 'tab' => 'leads'])->with('success', 'Fonte de lead adicionada.');
    }

    public function update(Request $request, Client $client, ClientLeadSource $leadSource): RedirectResponse
    {
        abort_unless($leadSource->client_id === $client->id, 403);

        $data = $this->validated($request, $leadSource);

        $leadSource->update($data);

        return redirect()->route('clients.show', [$client, 'tab' => 'leads'])->with('success', 'Fonte de lead atualizada.');
    }

    public function destroy(Client $client, ClientLeadSource $leadSource): RedirectResponse
    {
        abort_unless($leadSource->client_id === $client->id, 403);

        $leadSource->delete();

        return redirect()->route('clients.show', [$client, 'tab' => 'leads'])->with('success', 'Fonte de lead removida.');
    }

    private function validated(Request $request, ?ClientLeadSource $ignoring = null): array
    {
        $data = $request->validate([
            'lead_channel_id' => ['required', 'uuid', 'exists:lead_channels,id'],
            'external_id'     => [
                'required', 'string', 'max:150',
                'unique:client_lead_sources,external_id,' . ($ignoring?->id ?? 'NULL') . ',id,lead_channel_id,' . $request->lead_channel_id,
            ],
            'label' => ['nullable', 'string', 'max:150'],
        ]);

        // Checkbox ausente no POST = desmarcado — precisa entrar explícito no
        // update, senão a chave nem aparece em $data e o valor antigo persiste.
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientLinkController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $data = $request->validate([
            'type'        => 'required|string|max:50',
            'type_custom' => 'required_if:type,outros|nullable|string|max:100',
            'url'         => 'required|url|max:500',
            'notes'       => 'nullable|string',
        ]);

        $data['client_id']  = $client->id;
        $data['created_by'] = Auth::id();

        ClientLink::create($data);

        return redirect()->route('clients.show', [$client, 'tab' => 'links'])
            ->with('success', 'Link adicionado.');
    }

    public function destroy(Client $client, ClientLink $link)
    {
        abort_unless($link->client_id === $client->id, 403);

        $link->delete();

        return redirect()->route('clients.show', [$client, 'tab' => 'links'])
            ->with('success', 'Link removido.');
    }
}

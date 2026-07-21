<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientContextController extends Controller
{
    public function switch(Request $request, Client $client): RedirectResponse
    {
        $contact = Auth::guard('portal')->user();

        abort_unless($contact->clients()->where('clients.id', $client->id)->exists(), 403);

        $request->session()->put('portal_current_client_id', $client->id);

        return redirect()->route('portal.dashboard');
    }
}

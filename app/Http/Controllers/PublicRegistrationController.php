<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class PublicRegistrationController extends Controller
{
    public function show(string $token)
    {
        $client = Client::where('registration_token', $token)->firstOrFail();

        if ($client->isRegistrationComplete()) {
            return view('public.registration-done');
        }

        return view('public.client-registration', compact('client'));
    }

    public function submit(Request $request, string $token)
    {
        $client = Client::where('registration_token', $token)->firstOrFail();

        if ($client->isRegistrationComplete()) {
            return redirect()->route('clients.register', $token);
        }

        $data = $request->validate([
            'company_name'            => 'required|string|max:255',
            'tax_id'                  => 'nullable|string|max:20',
            'contact_email'           => 'required|email|max:255',
            'contact_phone'           => 'required|string|max:30',
            'address'                 => 'nullable|string',
            'zip_code'                => 'nullable|string|max:10',
            'responsible_name'        => 'required|string|max:255',
            'responsible_birthdate'   => 'nullable|date',
            'responsible_rg'          => 'nullable|string|max:30',
            'responsible_cpf'         => 'nullable|string|max:20',
            'responsible_address'     => 'nullable|string',
            'responsible_marital_status' => 'nullable|string|max:30',
            'payment_method'          => 'required|in:pix,cartao,boleto',
            'billing_day'             => 'required|integer|in:10,15,20',
            'billing_email'           => 'nullable|email|max:255',
            'billing_whatsapp'        => 'nullable|string|max:30',
            'billing_notes'           => 'nullable|string',
        ]);

        $client->update(array_merge($data, [
            'registration_completed_at' => now(),
        ]));

        return view('public.registration-done');
    }
}

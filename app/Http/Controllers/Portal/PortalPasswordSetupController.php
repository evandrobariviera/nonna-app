<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalPasswordSetupToken;
use App\Services\SystemNotificationService;
use Illuminate\Http\Request;

class PortalPasswordSetupController extends Controller
{
    public function show(string $token)
    {
        $setupToken = PortalPasswordSetupToken::where('token', $token)
            ->with(['client', 'contact'])
            ->firstOrFail();

        if (! $setupToken->isValid()) {
            return view('portal.password-setup.expired', compact('setupToken'));
        }

        return view('portal.password-setup.show', compact('setupToken'));
    }

    public function submit(Request $request, string $token)
    {
        $setupToken = PortalPasswordSetupToken::where('token', $token)
            ->with(['client', 'contact'])
            ->firstOrFail();

        if (! $setupToken->isValid()) {
            return redirect()->route('portal.password-setup.show', $token);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $setupToken->contact->update(['password' => $data['password']]);
        $setupToken->update(['used_at' => now()]);

        if ($setupToken->requested_by) {
            app(SystemNotificationService::class)->send(
                'portal.senha_definida',
                collect([$setupToken->requestedBy]),
                [
                    'client_name'  => $setupToken->client->displayName(),
                    'contact_name' => $setupToken->contact->name,
                ],
                route('clients.show', [$setupToken->client, 'tab' => 'portal']),
                $setupToken,
                $setupToken->client->organization_id,
            );
        }

        return view('portal.password-setup.thanks', compact('setupToken'));
    }
}

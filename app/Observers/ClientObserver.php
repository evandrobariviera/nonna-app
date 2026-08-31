<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\ClientOnboardingService;

class ClientObserver
{
    public function updated(Client $client): void
    {
        // Cadastro público concluído (PublicRegistrationController::submit) —
        // dispara a segunda etapa da esteira de onboarding.
        if ($client->wasChanged('registration_completed_at') && $client->registration_completed_at !== null) {
            app(ClientOnboardingService::class)->onRegistrationComplete($client);
        }
    }
}

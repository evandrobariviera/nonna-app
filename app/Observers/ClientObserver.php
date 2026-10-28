<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Services\ClientFirstCycleService;
use App\Services\ClientOnboardingService;

class ClientObserver
{
    // Cliente ativo nasce com o primeiro ciclo: planejamento de 90 dias + projeto Onboarding.
    // Lead ainda não é cliente — o ciclo nasce quando ele vira Ativo (updated, abaixo).
    public function created(Client $client): void
    {
        if ($client->status === 'active') {
            app(ClientFirstCycleService::class)->start($client);
        }
    }

    public function updated(Client $client): void
    {
        if ($client->wasChanged('status') && $client->status === 'active') {
            app(ClientFirstCycleService::class)->start($client); // não faz nada se já tiver planejamento
        }

        // Cadastro público concluído (PublicRegistrationController::submit) —
        // dispara a segunda etapa da esteira de onboarding.
        if ($client->wasChanged('registration_completed_at') && $client->registration_completed_at !== null) {
            app(ClientOnboardingService::class)->onRegistrationComplete($client);
        }

        // Nome dos planejamentos é derivado do apelido (ver MacroPlan::standardTitleFor()).
        if ($client->wasChanged('nickname') || $client->wasChanged('company_name')) {
            MacroPlan::refreshTitlesFor($client);
        }
    }
}

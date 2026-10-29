<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Aba da Central de Performance sem o módulo liberado leva para a vitrine
 * dele ("Quero contratar") — ver Client::portalModuleActive().
 */
trait RequiresPortalModule
{
    protected function requirePortalModule(string $moduleKey): void
    {
        if (!app('currentPortalClient')->portalModuleActive($moduleKey)) {
            throw new HttpResponseException(redirect()->route('portal.performance.showcase', $moduleKey));
        }
    }
}

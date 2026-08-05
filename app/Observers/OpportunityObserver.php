<?php

namespace App\Observers;

use App\Models\Opportunity;
use App\Services\AutomationEngine;

class OpportunityObserver
{
    public function updated(Opportunity $opportunity): void
    {
        if ($opportunity->wasChanged('stage')) {
            AutomationEngine::evaluate('status_changed', $opportunity, [
                'field' => 'stage',
                'from'  => $opportunity->getOriginal('stage'),
                'to'    => $opportunity->stage,
            ]);
        }
    }
}

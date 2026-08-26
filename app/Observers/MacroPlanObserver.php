<?php

namespace App\Observers;

use App\Models\MacroPlan;
use App\Services\AutomationEngine;

class MacroPlanObserver
{
    public function updated(MacroPlan $macroPlan): void
    {
        if ($macroPlan->wasChanged('status')) {
            AutomationEngine::evaluate('status_changed', $macroPlan, [
                'field' => 'status',
                'from'  => $macroPlan->getOriginal('status'),
                'to'    => $macroPlan->status,
            ]);
        }
    }
}

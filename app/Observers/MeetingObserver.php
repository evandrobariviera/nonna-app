<?php

namespace App\Observers;

use App\Models\Meeting;
use App\Services\AutomationEngine;

class MeetingObserver
{
    public function updated(Meeting $meeting): void
    {
        if ($meeting->wasChanged('status')) {
            AutomationEngine::evaluate('status_changed', $meeting, [
                'field' => 'status',
                'from'  => $meeting->getOriginal('status'),
                'to'    => $meeting->status,
            ]);
        }
    }
}

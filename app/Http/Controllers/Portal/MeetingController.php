<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Illuminate\View\View;

class MeetingController extends Controller
{
    public function index(): View
    {
        $client = app('currentPortalClient');

        $meetings = Meeting::where('client_id', $client->id)
            ->orderByDesc('scheduled_at')
            ->get();

        $upcoming = $meetings->whereIn('status', ['para_agendar', 'agendada']);
        $past     = $meetings->whereIn('status', ['pos_reuniao', 'revisao_ata', 'realizada', 'cancelada']);

        return view('portal.meetings.index', compact('client', 'upcoming', 'past'));
    }

    public function show(Meeting $meeting): View
    {
        $client = app('currentPortalClient');

        abort_if($meeting->client_id !== $client->id, 403);

        return view('portal.meetings.show', compact('client', 'meeting'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MeetingController extends Controller
{
    public function index(Request $request)
    {
        $query = Meeting::with(['client', 'organizer', 'participants'])
            ->orderBy('scheduled_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        $meetings = $query->paginate(20)->withQueryString();
        $clients  = Client::orderBy('company_name')->get(['id', 'company_name']);

        return view('meetings.index', compact('meetings', 'clients'));
    }

    public function create()
    {
        $users        = User::orderBy('name')->get(['id', 'name']);
        $clients      = Client::orderBy('company_name')->get(['id', 'company_name']);
        $opportunities = Opportunity::orderBy('title')->get(['id', 'title', 'client_id']);

        return view('meetings.create', compact('users', 'clients', 'opportunities'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:200',
            'type'             => 'required|string|max:50',
            'modality'         => 'required|string|max:30',
            'status'           => 'required|string|max:30',
            'scheduled_at'     => 'required|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'client_id'        => 'nullable|uuid|exists:clients,id',
            'opportunity_id'   => 'nullable|uuid|exists:opportunities,id',
            'organized_by'     => 'required|exists:users,id',
            'location'         => 'nullable|string|max:255',
            'online_link'      => 'nullable|url|max:500',
            'agenda'           => 'nullable|string',
            'participants'     => 'nullable|array',
            'participants.*'   => 'exists:users,id',
        ]);

        $meeting = Meeting::create([
            ...$data,
            'created_by' => Auth::id(),
        ]);

        if (!empty($data['participants'])) {
            $meeting->participants()->sync($data['participants']);
        }

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Reunião criada com sucesso.');
    }

    public function show(Meeting $meeting)
    {
        $meeting->load(['client', 'opportunity', 'organizer', 'participants', 'createdBy']);

        return view('meetings.show', compact('meeting'));
    }

    public function edit(Meeting $meeting)
    {
        $meeting->load(['participants']);
        $users         = User::orderBy('name')->get(['id', 'name']);
        $clients       = Client::orderBy('company_name')->get(['id', 'company_name']);
        $opportunities = Opportunity::orderBy('title')->get(['id', 'title', 'client_id']);

        return view('meetings.edit', compact('meeting', 'users', 'clients', 'opportunities'));
    }

    public function update(Request $request, Meeting $meeting)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:200',
            'type'             => 'required|string|max:50',
            'modality'         => 'required|string|max:30',
            'status'           => 'required|string|max:30',
            'scheduled_at'     => 'required|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'client_id'        => 'nullable|uuid|exists:clients,id',
            'opportunity_id'   => 'nullable|uuid|exists:opportunities,id',
            'organized_by'     => 'required|exists:users,id',
            'location'         => 'nullable|string|max:255',
            'online_link'      => 'nullable|url|max:500',
            'agenda'           => 'nullable|string',
            'ata'              => 'nullable|string',
            'next_steps'       => 'nullable|string',
            'participants'     => 'nullable|array',
            'participants.*'   => 'exists:users,id',
        ]);

        // Mark ata_recorded_at when ata is filled for the first time
        if (!empty($data['ata']) && empty($meeting->ata)) {
            $data['ata_recorded_at'] = now();
        }

        $meeting->update($data);

        $meeting->participants()->sync($data['participants'] ?? []);

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Reunião atualizada.');
    }

    public function updateStatus(Request $request, Meeting $meeting)
    {
        $data = $request->validate([
            'status' => 'required|string|max:30',
        ]);

        $meeting->update(['status' => $data['status']]);

        return redirect()->back()->with('success', 'Status atualizado.');
    }

    public function destroy(Meeting $meeting)
    {
        $meeting->delete();

        return redirect()->route('meetings.index')
            ->with('success', 'Reunião removida.');
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $client = auth()->user()->client;

        $query = Task::where('is_ticket', true)
            ->where('client_id', $client->id)
            ->orderByDesc('created_at');

        if (!$request->boolean('mostrar_fechados')) {
            $query->whereNotIn('status', ['concluido', 'cancelado']);
        }

        $tickets = $query->get();

        return view('portal.tickets.index', compact('client', 'tickets'));
    }

    public function show(Task $task): View
    {
        $client = auth()->user()->client;

        abort_if(!$task->is_ticket || $task->client_id !== $client->id, 403);

        $deliverables = $task->attachments()->where('is_deliverable', true)->get();

        return view('portal.tickets.show', compact('client', 'task', 'deliverables'));
    }
}

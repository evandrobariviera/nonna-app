<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\TaskApprovalRound;
use Illuminate\Http\Request;

class ApprovalDashboardController extends Controller
{
    public function index(Request $request)
    {
        $query = TaskApprovalRound::with(['task.client', 'tokens.contact', 'submittedBy'])
            ->whereHas('task')
            ->orderByDesc('submitted_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('client_id')) {
            $query->whereHas('task', fn ($q) => $q->where('client_id', $request->client_id));
        }

        $rounds = $query->paginate(30)->withQueryString();

        $stats = [
            'pending'        => TaskApprovalRound::where('status', 'pending')->count(),
            'changes'        => TaskApprovalRound::where('status', 'changes_requested')->count(),
            'approved_today' => TaskApprovalRound::where('status', 'approved')
                ->whereDate('resolved_at', today())->count(),
            'approved_total' => TaskApprovalRound::where('status', 'approved')->count(),
        ];

        $clientIds = TaskApprovalRound::whereHas('task')
            ->with('task:id,client_id')
            ->get()
            ->pluck('task.client_id')
            ->filter()
            ->unique();

        $clients = Client::whereIn('id', $clientIds)
            ->orderBy('company_name')
            ->get(['id', 'company_name']);

        return view('approvals.index', compact('rounds', 'stats', 'clients'));
    }
}

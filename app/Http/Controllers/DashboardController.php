<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskApprovalRound;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $activeSprint = Sprint::where('status', 'active')->first();
        if ($activeSprint) {
            $activeSprint->load('tasks');
            $sprintTotal = $activeSprint->tasks->whereNotIn('status', ['cancelado'])->count();
            $sprintDone  = $activeSprint->tasks->where('status', 'concluido')->count();
            $sprintProgress = $sprintTotal > 0 ? (int) round(($sprintDone / $sprintTotal) * 100) : 0;
        } else {
            $sprintTotal = $sprintDone = $sprintProgress = 0;
        }

        $myTasks = Task::where(function ($q) use ($userId) {
                $q->where('executor_id', $userId)
                  ->orWhereHas('executors', fn ($q2) => $q2->where('users.id', $userId));
            })
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->whereNotNull('due_date')
            ->where('due_date', '<=', today())
            ->with(['client', 'project'])
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        $myMeetings = Meeting::where(function ($q) use ($userId) {
                $q->where('organized_by', $userId)
                  ->orWhereHas('participants', fn ($q2) => $q2->where('users.id', $userId));
            })
            ->where('status', 'agendada')
            ->where('scheduled_at', '>=', now())
            ->with('client')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();

        $myPendingApprovals = TaskApprovalRound::where('submitted_by', $userId)
            ->where('status', 'pending')
            ->with('task.client')
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'activeSprint', 'sprintTotal', 'sprintDone', 'sprintProgress',
            'myTasks', 'myMeetings', 'myPendingApprovals'
        ));
    }
}

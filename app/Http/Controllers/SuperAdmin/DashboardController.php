<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total'     => Organization::count(),
            'active'    => Organization::where('status', 'active')->count(),
            'trial'     => Organization::where('status', 'trial')->count(),
            'suspended' => Organization::where('status', 'suspended')->count(),
        ];

        $byPlan = Organization::selectRaw('plan, count(*) as total')
            ->groupBy('plan')
            ->pluck('total', 'plan');

        $recent = Organization::with('owner')->latest()->limit(5)->get();

        return view('superadmin.dashboard', compact('stats', 'byPlan', 'recent'));
    }
}

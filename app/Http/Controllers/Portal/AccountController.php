<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $client = auth()->user()->client()->with([
            'contacts',
            'adAccounts',
            'credentials',
        ])->firstOrFail();

        return view('portal.account.index', compact('client'));
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $client = app('currentPortalClient');
        $client->load(['contacts', 'adAccounts', 'credentials']);

        return view('portal.account.index', compact('client'));
    }
}

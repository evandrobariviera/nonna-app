<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClientAdBillingDocument;
use Illuminate\View\View;

class BillingDocumentController extends Controller
{
    public function index(): View
    {
        $client = app('currentPortalClient');

        $accountIds = $client->adAccounts()->pluck('id');

        $documents = ClientAdBillingDocument::whereIn('client_ad_account_id', $accountIds)
            ->with('adAccount')
            ->orderByDesc('created_at')
            ->get();

        return view('portal.billing.index', compact('client', 'documents'));
    }
}

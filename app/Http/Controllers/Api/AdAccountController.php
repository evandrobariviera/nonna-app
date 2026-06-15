<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientAdAccount;
use Illuminate\Http\JsonResponse;

class AdAccountController extends Controller
{
    // GET /api/ad-accounts
    // n8n chama para saber quais contas sincronizar
    public function index(): JsonResponse
    {
        $org = app('currentOrganization');

        $accounts = ClientAdAccount::query()
            ->where('organization_id', $org->id)
            ->where('status', 'active')
            ->with('client:id,company_name,status')
            ->get()
            ->map(fn ($account) => [
                'id'             => $account->id,
                'client_id'      => $account->client_id,
                'client_name'    => $account->client?->company_name,
                'platform'       => $account->platform,
                'account_id'     => $account->account_id,
                'account_name'   => $account->account_name,
                'status'         => $account->status,
            ]);

        return response()->json(['data' => $accounts]);
    }
}

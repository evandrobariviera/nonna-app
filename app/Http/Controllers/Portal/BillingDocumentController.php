<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AdStatementDelivery;
use App\Models\ClientAdAccount;
use App\Services\Billing\AccountStatement;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Prestação de contas no Portal (antiga "Boletos"): por conta de anúncio,
 * mês a mês — notas fiscais, boletos/PIX e o extrato (investido, créditos,
 * saldo). Abrir a página conta como "visto" para os envios ainda não abertos.
 */
class BillingDocumentController extends Controller
{
    public function index(): View
    {
        $client = app('currentPortalClient');

        $accounts = $client->adAccounts()->whereIn('platform', ClientAdAccount::billingPlatforms())->get();

        $statements = $accounts->mapWithKeys(fn (ClientAdAccount $a) => [
            $a->id => collect(AccountStatement::forAccount($a, Carbon::parse('2026-07-01')))
                ->filter(fn ($row) => $row['consumed'] > 0 || $row['documents']->isNotEmpty())
                ->values(),
        ])->filter->isNotEmpty();

        AdStatementDelivery::where('client_id', $client->id)->whereNull('viewed_at')
            ->update(['viewed_at' => now()]);

        return view('portal.billing.index', [
            'client'     => $client,
            'accounts'   => $accounts->keyBy('id'),
            'statements' => $statements,
        ]);
    }
}

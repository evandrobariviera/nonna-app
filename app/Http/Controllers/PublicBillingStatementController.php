<?php

namespace App\Http\Controllers;

use App\Models\AdStatementDelivery;
use App\Models\ClientAdAccount;
use App\Services\Billing\AccountStatement;
use Illuminate\View\View;

/**
 * Página que o cliente abre pelo link do aviso (WhatsApp/e-mail), sem login:
 * as notas fiscais e o extrato do mês enviado, mais os meses anteriores.
 * O link é assinado (expira em 180 dias) e cada abertura fica registrada.
 */
class PublicBillingStatementController extends Controller
{
    public function show(string $delivery): View
    {
        $delivery = AdStatementDelivery::withoutGlobalScopes()->with('client')->findOrFail($delivery);
        $client = $delivery->client;

        $delivery->forceFill([
            'viewed_at'  => $delivery->viewed_at ?? now(),
            'view_count' => $delivery->view_count + 1,
        ])->save();

        $accounts = ClientAdAccount::where('client_id', $client->id)
            ->whereIn('platform', ClientAdAccount::billingPlatforms())
            ->get();

        $from = $delivery->month->copy()->subMonths(5);
        $statements = $accounts->mapWithKeys(fn (ClientAdAccount $a) => [
            $a->id => collect(AccountStatement::forAccount($a, $from, $delivery->month))
                ->filter(fn ($row) => $row['consumed'] > 0 || $row['documents']->isNotEmpty())
                ->values(),
        ])->filter->isNotEmpty();

        return view('billing-statements.public', [
            'client'     => $client,
            'delivery'   => $delivery,
            'accounts'   => $accounts->keyBy('id'),
            'statements' => $statements,
        ]);
    }
}

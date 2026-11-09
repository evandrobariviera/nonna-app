<?php

namespace App\Http\Controllers;

use App\Models\AdAccountStatement;
use App\Models\AdStatementDelivery;
use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Models\ClientAdBillingDocument;
use App\Services\Billing\AccountStatement;
use App\Services\Billing\StatementDeliveryService;
use App\Support\UploadOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Prestação de contas (aba da Central de Campanhas): notas fiscais dos
 * anúncios mês a mês, extrato de cada conta e envio ao cliente.
 */
class BillingStatementController extends Controller
{
    /** Primeiro mês com dados de gasto no App (sincronização direta, jul/2026). */
    private const HISTORY_FROM = '2026-07-01';

    public function index(Request $request): View
    {
        $month = $this->month($request->get('mes')) ?? now()->subMonthNoOverflow()->startOfMonth();
        $clientId = $request->get('client_id');
        $mine = $clientId === 'meus';

        $rows = AccountStatement::overview($month, $mine ? null : $clientId, $mine ? Auth::id() : null);

        return view('billing-statements.index', [
            'month'    => $month,
            'groups'   => $rows->groupBy(fn ($r) => $r->client->id),
            'clients'  => AccountStatement::clientsWithAccounts(),
            'clientId' => $mine ? null : $clientId,
            'mine'     => $mine,
            'months'   => collect(range(0, 5))->map(fn ($i) => now()->subMonthsNoOverflow($i)->startOfMonth()),
        ]);
    }

    public function show(Request $request, Client $client): View
    {
        $accounts = $client->adAccounts()->whereIn('platform', ClientAdAccount::billingPlatforms())->get();

        $statements = $accounts->mapWithKeys(fn (ClientAdAccount $a) => [
            $a->id => AccountStatement::forAccount($a, Carbon::parse(self::HISTORY_FROM)),
        ]);

        return view('billing-statements.show', [
            'client'       => $client,
            'accounts'     => $accounts,
            'statements'   => $statements,
            'deliveries'   => AdStatementDelivery::with('sentBy:id,name')->where('client_id', $client->id)->orderByDesc('sent_at')->limit(12)->get(),
            'defaultMonth' => $this->month($request->get('mes')) ?? now()->subMonthNoOverflow()->startOfMonth(),
        ]);
    }

    /** Sobe uma ou várias notas de uma conta e (por padrão) já avisa o cliente. */
    public function storeInvoices(Request $request, Client $client, StatementDeliveryService $delivery): RedirectResponse
    {
        $data = $request->validate([
            'client_ad_account_id' => 'required|uuid',
            'month'                => 'required|date_format:Y-m',
            'files'                => 'required|array|min:1|max:20',
            'files.*'              => 'file|max:20480',
            'amounts'              => 'nullable|array',
            'amounts.*'            => 'nullable|string|max:20',
        ], [
            'files.required' => 'Escolha pelo menos um arquivo de nota fiscal.',
            'files.*.max'    => 'Cada arquivo pode ter até 20 MB.',
        ]);

        $account = $client->adAccounts()->findOrFail($data['client_ad_account_id']);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $disk = config('filesystems.default', 'r2');

        $documents = collect($request->file('files'))->values()->map(function ($file, $i) use ($account, $month, $disk, $data) {
            $mime = $file->getMimeType();

            return ClientAdBillingDocument::create([
                'client_ad_account_id' => $account->id,
                'type'                 => 'nota',
                'amount'               => self::parseMoney($data['amounts'][$i] ?? null),
                'reference_month'      => $month->toDateString(),
                'filename'             => $file->getClientOriginalName(),
                'disk_path'            => $file->store("client-ad-billing/{$account->id}/notas", UploadOptions::forStore($mime, $disk)),
                'disk'                 => $disk,
                'mime_type'            => $mime,
                'size'                 => $file->getSize(),
                'created_by'           => Auth::id(),
            ]);
        });

        $message = $documents->count() === 1 ? 'Nota fiscal salva' : $documents->count() . ' notas fiscais salvas';

        if ($request->boolean('notify', true)) {
            $delivery->send($client, $month, $documents, Auth::id());
            $message .= ' e cliente avisado';
        }

        return back()->with('success', $message . '.');
    }

    /** Reenvia ao cliente todas as notas de um mês (num aviso só). */
    public function send(Request $request, Client $client, StatementDeliveryService $delivery): RedirectResponse
    {
        $month = $this->month($request->input('month'));
        abort_unless($month, 422);

        $documents = ClientAdBillingDocument::whereIn('client_ad_account_id', $client->adAccounts()->pluck('id'))
            ->where('type', 'nota')
            ->where('reference_month', $month->toDateString())
            ->get();

        if ($documents->isEmpty()) {
            return back()->withErrors(['month' => 'Nenhuma nota fiscal deste mês para enviar.']);
        }

        $delivery->send($client, $month, $documents, Auth::id());

        return back()->with('success', 'Notas de ' . StatementDeliveryService::monthLabel($month) . ' enviadas ao cliente.');
    }

    /** Saldo inicial manual e observação do mês. */
    public function updateStatement(Request $request, ClientAdAccount $adAccount): RedirectResponse
    {
        $data = $request->validate([
            'month'           => 'required|date_format:Y-m',
            'opening_balance' => 'nullable|string|max:20',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $statement = AdAccountStatement::firstOrNew(['client_ad_account_id' => $adAccount->id, 'month' => $month->toDateString()]);
        $statement->organization_id ??= $adAccount->client?->organization_id;
        $statement->notes = $data['notes'] ?? null;

        if ($request->has('opening_balance')) {
            $value = self::parseMoney($data['opening_balance']);
            $statement->opening_balance = $value;
            $statement->opening_balance_source = $value === null ? null : 'manual';
        }

        $statement->save();

        return back()->with('success', 'Extrato atualizado.');
    }

    private function month(?string $value): ?Carbon
    {
        return $value && preg_match('/^\d{4}-\d{2}$/', $value) ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : null;
    }

    /** "1.234,56", "1234.56" ou "1234,56" → 1234.56 */
    public static function parseMoney(?string $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/[^\d,.\-]/', '', $value);
        if (str_contains($value, ',')) {
            $value = str_replace(',', '.', str_replace('.', '', $value));
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}

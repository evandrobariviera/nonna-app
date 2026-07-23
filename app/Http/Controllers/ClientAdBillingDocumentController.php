<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Models\ClientAdBillingDocument;
use App\Services\NotificationDispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ClientAdBillingDocumentController extends Controller
{
    public function store(Request $request, Client $client, ClientAdAccount $adAccount, NotificationDispatchService $notifier)
    {
        abort_unless($adAccount->client_id === $client->id, 403);

        $data = $request->validate([
            'type'     => 'required|in:boleto,pix',
            'amount'   => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'pix_code' => 'required_if:type,pix|nullable|string',
            'file'     => 'required_if:type,boleto|nullable|file|max:20480', // 20 MB
            'notes'    => 'nullable|string',
        ]);

        if (empty($data['pix_code']) && !$request->hasFile('file')) {
            return back()->withErrors(['file' => 'Envie o arquivo do boleto ou informe o código PIX.']);
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $disk = config('filesystems.default', 'r2');
            $path = $file->store("client-ad-billing/{$adAccount->id}", $disk);

            $data['filename']  = $file->getClientOriginalName();
            $data['disk_path'] = $path;
            $data['disk']      = $disk;
            $data['mime_type'] = $file->getMimeType();
            $data['size']      = $file->getSize();
        }

        unset($data['file']);
        $data['client_ad_account_id'] = $adAccount->id;
        $data['created_by']           = Auth::id();

        $document = ClientAdBillingDocument::create($data);

        $accountUpdate = ['last_billing_sent_at' => now()];

        // Contas boleto/PIX fora do Meta não têm saldo por API — o valor
        // informado aqui é o "depósito" que credita o livro-caixa mantido em
        // SyncAdPlatforms::debitLedgerBalance() (ver ClientAdAccount::usesLedgerBalance()).
        if ($document->amount && $adAccount->usesLedgerBalance()) {
            $accountUpdate['balance'] = (float) ($adAccount->balance ?? 0) + (float) $document->amount;
            $accountUpdate['balance_source'] = 'ledger';
        }

        $adAccount->update($accountUpdate);
        $adAccount->markAwaitingPayment();

        $notifier->send('financeiro', $client, [
            'conta'      => $adAccount->platformLabel() . ' · ' . $adAccount->account_id,
            'tipo'       => $document->typeLabel(),
            'valor'      => $document->amount ? 'R$ ' . number_format((float) $document->amount, 2, ',', '.') : '—',
            'vencimento' => $document->due_date?->format('d/m/Y') ?? '—',
        ], $document->url());

        $document->update(['notified_at' => now()]);

        return redirect()->route('clients.show', [$client, 'tab' => 'contas'])
            ->with('success', 'Boleto/PIX enviado e cliente notificado.');
    }

    public function destroy(Client $client, ClientAdAccount $adAccount, ClientAdBillingDocument $document)
    {
        abort_unless($adAccount->client_id === $client->id, 403);
        abort_unless($document->client_ad_account_id === $adAccount->id, 404);

        if ($document->hasFile()) {
            Storage::disk($document->disk)->delete($document->disk_path);
        }

        $document->delete();

        return redirect()->route('clients.show', [$client, 'tab' => 'contas'])
            ->with('success', 'Documento removido.');
    }
}

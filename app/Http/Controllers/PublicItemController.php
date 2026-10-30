<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientItemNotice;
use App\Models\MacroPlan;
use App\Models\Project;

/**
 * Link sem senha do aviso ao cliente: só leitura, abre apenas o documento daquele item.
 * Sem documento, mostra o resumo, pra o link do WhatsApp nunca dar em página vazia.
 */
class PublicItemController extends Controller
{
    public function show(string $token)
    {
        $notice = ClientItemNotice::where('token', $token)->firstOrFail();
        $item   = $notice->resolveItem();
        $client = Client::withoutGlobalScopes()->find($notice->client_id);

        abort_unless($item && $client && $client->status !== 'inactive', 404);

        $isPlan   = $item instanceof MacroPlan;
        $plan     = $item instanceof Project && $item->macro_plan_id
            ? MacroPlan::withoutGlobalScopes()->find($item->macro_plan_id)
            : null;
        $document = $item->mainDocument();

        return response()
            ->view('public.item', compact('item', 'client', 'isPlan', 'plan', 'document'))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}

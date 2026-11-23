<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientItemNotice;
use App\Models\MacroPlan;
use App\Models\Project;
use App\Services\Documents\ClientDocuments;

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
        // Documento em campos (24/11): o planejamento abre com uma aba para cada campanha/projeto.
        $fieldDocument = $isPlan ? ClientDocuments::forPlan($item) : ClientDocuments::forProject($item);
        $parts = $fieldDocument && $isPlan ? ClientDocuments::planItems($item) : collect();

        return response()
            ->view('public.item', compact('item', 'client', 'isPlan', 'plan', 'document', 'fieldDocument', 'parts', 'token'))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    /** Versão do cliente do documento em campos: o do item do link ou (planejamento) de uma das campanhas dele. */
    public function document(string $token, ?string $part = null)
    {
        $notice = ClientItemNotice::where('token', $token)->firstOrFail();
        $item   = $notice->resolveItem();
        $client = Client::withoutGlobalScopes()->find($notice->client_id);
        abort_unless($item && $client && $client->status !== 'inactive', 404);

        if ($part) {
            abort_unless($item instanceof MacroPlan, 404);
            $project = ClientDocuments::planItems($item)->firstWhere('id', $part);
            abort_unless($project, 404);
            $doc = ClientDocuments::docFor($project);
        } else {
            $doc = $item instanceof MacroPlan ? ClientDocuments::forPlan($item) : ClientDocuments::forProject($item);
        }
        abort_unless($doc, 404);

        return response(ClientDocuments::view($doc)->render())
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}

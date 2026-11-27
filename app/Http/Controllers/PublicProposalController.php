<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Organization;
use App\Models\Proposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Página da proposta pro cliente (/proposta/{token}) — o link que o vendedor manda e que
 * também vira PDF (botão "Baixar PDF" = imprimir do navegador, com folha de impressão própria).
 * Só mostra o que é do cliente: nada de preço de tabela, liberação, sugestão de upgrade ou avisos.
 * Rascunho/aguardando liberação: só a equipe logada vê (prévia); o cliente recebe 404.
 * Quem está logado na equipe não conta como visita.
 */
class PublicProposalController extends Controller
{
    public function show(string $token): View
    {
        $proposal = $this->find($token);
        $preview = auth()->check();
        abort_unless($proposal->isPublic() || $preview, 404);

        if (!$preview) {
            $proposal->timestamps = false;
            $proposal->forceFill(['public_views' => $proposal->public_views + 1, 'public_last_view_at' => now()])->saveQuietly();
            $proposal->timestamps = true;
        }

        $proposal->load('items');
        $opportunity = $proposal->opportunity()->withoutGlobalScopes()->with(['contact' => fn ($q) => $q->withoutGlobalScopes()])->first();
        $org = Organization::find($proposal->organization_id);

        // Descrição de cada item vem do catálogo (o item da proposta guarda só nome e preço)
        $ids = $proposal->items->pluck('catalog_item_id')->push($proposal->package_id)->filter()->unique();
        $descriptions = CatalogItem::withoutGlobalScopes()->whereIn('id', $ids)->pluck('description', 'id');

        // Versão encerrada: se a equipe mandou uma mais nova, aponta pra ela
        $newer = null;
        if ($proposal->status === 'rejected') {
            $newer = Proposal::withoutGlobalScopes()
                ->where('opportunity_id', $proposal->opportunity_id)
                ->where('version', '>', $proposal->version)
                ->whereIn('status', ['sent', 'accepted'])
                ->orderByDesc('version')->first();
        }

        return view('proposals.public', [
            'proposal'     => $proposal,
            'contact'      => $opportunity?->contact,
            'org'          => $org,
            'descriptions' => $descriptions,
            'newer'        => $newer,
            'whatsapp'     => CommerceReportController::whatsapp($org),
            'preview'      => $preview,
        ]);
    }

    /** "Quero fechar / tenho dúvidas": abre o WhatsApp da agência com a proposta citada. */
    public function talk(string $token): RedirectResponse
    {
        $proposal = $this->find($token);
        abort_unless($proposal->isPublic(), 404);
        $number = CommerceReportController::whatsapp(Organization::find($proposal->organization_id));
        if (!$number) {
            return redirect()->route('proposals.public', $token);
        }
        $text = "Oi! Vi a proposta \"{$proposal->title}\" (versão {$proposal->version}) e quero conversar.";

        return redirect()->away('https://wa.me/' . $number . '?text=' . rawurlencode($text));
    }

    private function find(string $token): Proposal
    {
        abort_unless(preg_match('/^[a-f0-9]{32}$/', $token), 404);

        return Proposal::withoutGlobalScopes()->where('public_token', $token)->firstOrFail();
    }
}

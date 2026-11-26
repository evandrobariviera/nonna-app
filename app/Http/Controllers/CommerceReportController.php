<?php

namespace App\Http\Controllers;

use App\Models\CommercePlace;
use App\Services\Commerce\ProspectReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Raio-x público da prospecção (/rx/{token}) — a página que a empresa abre pelo link
 * da mensagem de abordagem. Sem login; só lê o que está guardado (não consulta o Google).
 * Quem está logado na equipe vê a mesma página, mas a visita não conta como abertura.
 */
class CommerceReportController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $place = $this->find($token);
        $preview = auth()->check();

        if (!$preview && $place->status !== 'opted_out') {
            $place->forceFill(['report_views' => $place->report_views + 1, 'report_last_view_at' => now()])->saveQuietly();
        }

        return view('commerce.report', [
            'place'   => $place,
            'r'       => ProspectReport::build($place->loadMissing('city', 'segment')),
            'org'     => $place->organization_id ? \App\Models\Organization::find($place->organization_id) : null,
            'preview' => $preview,
            'optedOut'=> $place->status === 'opted_out',
        ]);
    }

    /** "Não quero receber mais mensagens" — vale na hora, sem confirmação por mensagem. */
    public function optOut(string $token): RedirectResponse
    {
        $place = $this->find($token);
        if (!auth()->check()) {
            $place->update(['status' => 'opted_out', 'opted_out_at' => now()]);
        }

        return redirect()->route('commerce.report.show', $token)->with('opted_out', true);
    }

    private function find(string $token): CommercePlace
    {
        abort_unless(preg_match('/^[a-f0-9]{32}$/', $token), 404);

        return CommercePlace::withoutGlobalScopes()->where('report_token', $token)->firstOrFail();
    }
}

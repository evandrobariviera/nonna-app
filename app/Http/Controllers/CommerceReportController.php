<?php

namespace App\Http\Controllers;

use App\Models\CommercePlace;
use App\Models\Organization;
use App\Services\Commerce\ProspectReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Raio-x público da prospecção (/rx/{token}) — a página que a empresa abre pelo link
 * da mensagem de abordagem. Sem login; só lê o que está guardado (não consulta o Google).
 * Quem está logado na equipe vê a mesma página, mas a visita (e o clique) não contam.
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
        $org = Organization::find($place->organization_id);

        return view('commerce.report', [
            'place'    => $place,
            'r'        => ProspectReport::build($place->loadMissing('city', 'segment')),
            'org'      => $org,
            'whatsapp' => self::whatsapp($org),
            'preview'  => $preview,
            'optedOut' => $place->status === 'opted_out',
        ]);
    }

    /** Botão "Quero melhorar meu raio-x": registra o interesse e abre o WhatsApp da agência. */
    public function talk(string $token): RedirectResponse
    {
        $place = $this->find($token);
        $number = self::whatsapp(Organization::find($place->organization_id));
        if (!$number) {
            return redirect()->route('commerce.report.show', $token);
        }
        if (!auth()->check()) {
            $place->forceFill(['report_cta_clicks' => $place->report_cta_clicks + 1, 'report_cta_at' => now()])->saveQuietly();
        }
        $name = $place->google_data['name'] ?? 'minha empresa';
        $text = "Oi! Vi o raio-x da {$name} e quero entender como melhorar.";

        return redirect()->away('https://wa.me/' . $number . '?text=' . rawurlencode($text));
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

    /** WhatsApp da agência pro botão do raio-x (Prospecção → Segmentos e cidades), só dígitos com DDI. */
    public static function whatsapp(?Organization $org): ?string
    {
        $digits = preg_replace('/\D/', '', (string) data_get($org?->settings, 'prospecting.whatsapp'));
        if (strlen($digits) < 10) {
            return null;
        }

        return strlen($digits) <= 11 ? '55' . $digits : $digits;
    }

    private function find(string $token): CommercePlace
    {
        abort_unless(preg_match('/^[a-f0-9]{32}$/', $token), 404);

        return CommercePlace::withoutGlobalScopes()->where('report_token', $token)->firstOrFail();
    }
}

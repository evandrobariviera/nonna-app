<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\Leads\LeadRuleDrafter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// Régua de lead qualificado + ticket médio do cliente (aba Leads da ficha).
// Salvar = revisar: é a partir daí que a IA passa a avaliar os leads dele.
class ClientLeadRuleController extends Controller
{
    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'average_ticket' => 'nullable|numeric|min:0|max:9999999999',
            'lead_rule'      => 'nullable|string|max:5000',
        ]);

        $rule = trim((string) ($data['lead_rule'] ?? '')) ?: null;
        $ruleChanged = $rule !== $client->lead_rule;

        $client->update([
            'average_ticket'        => $data['average_ticket'] ?? null,
            'lead_rule'             => $rule,
            'lead_rule_reviewed_at' => $rule ? ($ruleChanged || !$client->lead_rule_reviewed_at ? now() : $client->lead_rule_reviewed_at) : null,
            'lead_rule_reviewed_by' => $rule ? ($ruleChanged || !$client->lead_rule_reviewed_by ? Auth::id() : $client->lead_rule_reviewed_by) : null,
        ]);

        return redirect()->route('clients.show', [$client, 'tab' => 'leads'])->with('success', $rule
            ? 'Régua salva. A IA começa a avaliar os leads deste cliente na próxima hora.'
            : 'Ticket médio salvo. Sem régua, a IA não avalia os leads deste cliente.');
    }

    // Só devolve o texto pro campo — nada é gravado até alguém revisar e salvar.
    public function draft(Client $client, LeadRuleDrafter $drafter)
    {
        try {
            return response()->json(['rule' => $drafter->draft($client, Auth::id())]);
        } catch (\Throwable $e) {
            Log::warning('Rascunho da régua de lead falhou', ['client' => $client->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => $e instanceof \RuntimeException ? $e->getMessage() : 'Não deu pra rascunhar agora. Tente de novo.'], 422);
        }
    }
}

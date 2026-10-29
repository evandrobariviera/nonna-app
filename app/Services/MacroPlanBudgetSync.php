<?php

namespace App\Services;

use App\Models\Client;
use App\Models\MacroPlan;
use Illuminate\Support\Facades\Auth;

/**
 * Verba mensal do planejamento → Orçamento do cliente. Se o valor diverge do orçamento
 * atual, lança uma nova entrada no histórico (a anterior continua lá). Usado ao salvar as
 * Configurações do planejamento e ao editar o documento (MacroPlanController,
 * DocumentEditService).
 */
class MacroPlanBudgetSync
{
    /** "R$ 1.234,56", "1234,56", "1234.56", "1000" → float (null se vazio/inválido). */
    public static function parseMoney(?string $raw): ?float
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.,]/', '', $raw);
        if ($clean === '') {
            return null;
        }

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    /** Valor pra guardar em bloco1.verba_total sem separador de milhar ("1000" / "1000,50"). */
    public static function storeFormat(float $value): string
    {
        return fmod($value, 1.0) === 0.0 ? (string) (int) $value : number_format($value, 2, ',', '');
    }

    public static function sync(MacroPlan $macroplan, ?float $newValue, string $origin): void
    {
        if ($newValue === null) {
            return;
        }

        $client  = $macroplan->client ?? Client::find($macroplan->client_id);
        $current = $client->currentAdBudget();

        if ($current && abs((float) $current->monthly_budget - $newValue) < 0.01) {
            return;
        }

        $client->adBudgets()->create([
            'monthly_budget' => $newValue,
            'start_date'     => now()->toDateString(),
            'notes'          => "Atualizado {$origin} \"{$macroplan->title}\".",
            'created_by'     => Auth::id(),
        ]);
    }
}

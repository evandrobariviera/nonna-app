<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateContractTransactions extends Command
{
    protected $signature = 'financial:generate-contract-transactions';

    protected $description = 'Materializa lançamentos de receita previstos a partir de Contratos ativos (mensal/anual)';

    public function handle(): int
    {
        $organizations = Organization::whereIn('status', ['trial', 'active'])->get();

        $totalGenerated = 0;

        foreach ($organizations as $organization) {
            app()->instance('currentOrganization', $organization);

            $contracts = Contract::where('status', 'ativo')
                ->whereNotNull('fee_value')
                ->whereNotNull('billing_day')
                ->whereIn('fee_type', ['mensal', 'anual'])
                ->get();

            foreach ($contracts as $contract) {
                try {
                    if ($this->generateForContract($contract)) {
                        $totalGenerated++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('GenerateContractTransactions: falha ao gerar lançamento', [
                        'contract_id' => $contract->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Lançamentos gerados: {$totalGenerated}.");

        return self::SUCCESS;
    }

    private function generateForContract(Contract $contract): bool
    {
        $monthStart = now()->startOfMonth();

        if ($contract->fee_type === 'anual') {
            $anchor = $contract->start_date ?? $contract->signed_at;
            if (!$anchor || $anchor->month !== now()->month) {
                return false;
            }

            $alreadyGenerated = FinancialTransaction::where('contract_id', $contract->id)
                ->whereYear('due_date', now()->year)
                ->exists();
        } else {
            $alreadyGenerated = FinancialTransaction::where('contract_id', $contract->id)
                ->whereYear('due_date', $monthStart->year)
                ->whereMonth('due_date', $monthStart->month)
                ->exists();
        }

        if ($alreadyGenerated) {
            return false;
        }

        $day = min($contract->billing_day, $monthStart->daysInMonth);
        $dueDate = $monthStart->copy()->day($day);

        $category = FinancialCategory::where('type', 'receita')
            ->where('name', 'Mensalidade Cliente')
            ->first();

        FinancialTransaction::create([
            'type'        => 'entrada',
            'category_id' => $category?->id,
            'amount'      => $contract->fee_value,
            'due_date'    => $dueDate,
            'client_id'   => $contract->client_id,
            'contract_id' => $contract->id,
            'description' => "Mensalidade — {$contract->title}",
        ]);

        return true;
    }
}

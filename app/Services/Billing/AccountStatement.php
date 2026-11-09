<?php

namespace App\Services\Billing;

use App\Models\AdAccountStatement;
use App\Models\AdStatementDelivery;
use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Models\ClientAdBillingDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Extrato mensal da conta de anúncio — o que antes era a planilha
 * "[Cliente] Prestação de Contas" no Drive:
 *
 *   saldo no início + créditos (boleto/PIX) − consumido = saldo no fim
 *
 * mais as notas fiscais do mês. O consumido vem da sincronização (gasto nas
 * campanhas); a nota é o valor oficial — a diferença costuma ser pequena
 * (cliques inválidos que a plataforma desconta).
 *
 * O saldo só começa em START ("começamos do valor atual"): o saldo inicial
 * desse mês é calculado uma vez a partir do saldo de hoje e guardado.
 * Conta no cartão não tem saldo — o extrato mostra só consumo e notas.
 */
class AccountStatement
{
    public const START = '2026-11-01';

    /** Primeiro mês em que a nota é cobrada (o anterior ao START); antes disso as notas ficavam no Drive. */
    public const FIRST_INVOICE_MONTH = '2026-10-01';

    /** Até este dia do mês a prestação do mês anterior deve estar com o cliente. */
    public const DEADLINE_DAY = 10;

    /**
     * Extrato de uma conta, mês a mês (mais recente primeiro).
     *
     * @return array<int, array{month: Carbon, opening: ?float, credits: float, consumed: float, invoiced: float,
     *     closing: ?float, notes: ?string, opening_manual: bool, documents: Collection}>
     */
    public static function forAccount(ClientAdAccount $account, Carbon $from, ?Carbon $to = null): array
    {
        $to = ($to ?? now())->copy()->startOfMonth();
        $from = $from->copy()->startOfMonth();
        $start = Carbon::parse(self::START);

        $consumed = DB::connection('pgsql')->table('ad_daily_snapshots')
            ->where('client_ad_account_id', $account->id)
            ->where('entity_level', 'campaign')
            ->where('snapshot_date', '>=', $from->toDateString())
            ->selectRaw("to_char(snapshot_date, 'YYYY-MM') AS m, SUM(spend) AS spend")
            ->groupBy('m')
            ->pluck('spend', 'm');

        $documents = ClientAdBillingDocument::where('client_ad_account_id', $account->id)
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (ClientAdBillingDocument $d) => $d->referenceMonth()->format('Y-m'));

        $statements = AdAccountStatement::where('client_ad_account_id', $account->id)->get()
            ->keyBy(fn ($s) => $s->month->format('Y-m'));

        $hasBalance = self::hasBalance($account);
        if ($hasBalance) {
            $startOpening = self::ensureStartOpening($account, $consumed, $documents, $statements);
        }

        $rows = [];
        $closing = null;
        for ($m = $from->copy(); $m->lte($to); $m->addMonth()) {
            $key = $m->format('Y-m');
            $docs = $documents->get($key, collect());
            $credits = (float) $docs->filter->isCredit()->sum('amount');
            $invoiced = (float) $docs->where('type', 'nota')->sum('amount');
            $spent = round((float) ($consumed[$key] ?? 0), 2);
            $stmt = $statements->get($key);

            $opening = null;
            if ($hasBalance && $m->gte($start)) {
                $opening = $stmt?->opening_balance_source === 'manual'
                    ? (float) $stmt->opening_balance
                    : ($m->equalTo($start) ? $startOpening : $closing);
            }
            $closing = $opening !== null ? round($opening + $credits - $spent, 2) : null;

            $rows[] = [
                'month'          => $m->copy(),
                'opening'        => $opening,
                'credits'        => $credits,
                'consumed'       => $spent,
                'invoiced'       => $invoiced,
                'closing'        => $closing,
                'notes'          => $stmt?->notes,
                'opening_manual' => $stmt?->opening_balance_source === 'manual',
                'documents'      => $docs,
            ];
        }

        return array_reverse($rows);
    }

    /**
     * Visão de um mês para a aba Prestação de contas: cada conta que gastou ou
     * recebeu documento no mês, com consumido × notas e o último envio.
     */
    public static function overview(Carbon $month, ?string $clientId = null, ?int $responsibleUserId = null): Collection
    {
        $month = $month->copy()->startOfMonth();
        $key = $month->format('Y-m');

        $accounts = ClientAdAccount::with('client')
            ->whereIn('platform', ClientAdAccount::billingPlatforms())
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($responsibleUserId, fn ($q) => $q->where('responsible_user_id', $responsibleUserId))
            ->get();

        if ($accounts->isEmpty()) {
            return collect();
        }

        $consumed = DB::connection('pgsql')->table('ad_daily_snapshots')
            ->whereIn('client_ad_account_id', $accounts->pluck('id'))
            ->where('entity_level', 'campaign')
            ->whereBetween('snapshot_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->groupBy('client_ad_account_id')
            ->selectRaw('client_ad_account_id, SUM(spend) AS spend')
            ->pluck('spend', 'client_ad_account_id');

        $docs = ClientAdBillingDocument::whereIn('client_ad_account_id', $accounts->pluck('id'))
            ->where(fn ($q) => $q->where('reference_month', $month->toDateString())
                ->orWhere(fn ($n) => $n->whereNull('reference_month')
                    ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])))
            ->get()
            ->groupBy('client_ad_account_id');

        $deliveries = AdStatementDelivery::whereIn('client_id', $accounts->pluck('client_id')->unique())
            ->where('month', $month->toDateString())
            ->orderByDesc('sent_at')
            ->get()
            ->groupBy('client_id');

        return $accounts->map(function (ClientAdAccount $a) use ($consumed, $docs, $deliveries) {
            $accountDocs = $docs->get($a->id, collect());
            $notas = $accountDocs->where('type', 'nota');

            return (object) [
                'account'   => $a,
                'client'    => $a->client,
                'consumed'  => round((float) ($consumed[$a->id] ?? 0), 2),
                'credits'   => (float) $accountDocs->filter->isCredit()->sum('amount'),
                'invoices'  => $notas->count(),
                'invoiced'  => (float) $notas->sum('amount'),
                'delivery'  => $deliveries->get($a->client_id)?->first(),
            ];
        })
            ->filter(fn ($r) => $r->consumed > 0 || $r->invoices > 0 || $r->credits > 0)
            ->sortBy(fn ($r) => [$r->invoices > 0 ? 1 : 0, $r->client?->displayName()])
            ->values();
    }

    /**
     * Contas que gastaram no mês anterior e ainda não têm nota desse mês —
     * o bloco "Prestação de contas" da aba Hoje.
     */
    public static function pendingForToday(Collection $accountIds): array
    {
        $month = now()->subMonthNoOverflow()->startOfMonth();
        if ($accountIds->isEmpty()) {
            return ['month' => $month, 'items' => collect(), 'total' => 0, 'done' => 0];
        }

        $rows = self::overview($month)->whereIn('account.id', $accountIds->all())->filter(fn ($r) => $r->consumed > 0);
        $pending = $rows->where('invoices', 0)->values();

        return [
            'month' => $month,
            'items' => $pending,
            'total' => $rows->count(),
            'done'  => $rows->count() - $pending->count(),
        ];
    }

    /** Estado do prazo (dia 10): null = no prazo, soon = chegando, late = passou. */
    public static function deadlineState(): ?string
    {
        $day = now()->day;

        return $day > self::DEADLINE_DAY ? 'late' : ($day >= self::DEADLINE_DAY - 2 ? 'soon' : null);
    }

    /** Este mês já devia ter nota? (passou e é do período em que a nota é cobrada) */
    public static function expectsInvoice(Carbon $month): bool
    {
        return $month->gte(Carbon::parse(self::FIRST_INVOICE_MONTH)) && $month->lt(now()->startOfMonth());
    }

    /** Pré-paga com saldo conhecido (Meta "Saldo disponível" ou livro-caixa). */
    public static function hasBalance(ClientAdAccount $account): bool
    {
        return $account->balance !== null && $account->payment_method !== 'cartao';
    }

    /** Clientes que têm alguma conta com prestação de contas. */
    public static function clientsWithAccounts(): Collection
    {
        return Client::where('status', '!=', 'inactive')
            ->whereHas('adAccounts', fn ($q) => $q->whereIn('platform', ClientAdAccount::billingPlatforms()))
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'nickname']);
    }

    /**
     * Saldo inicial do primeiro mês, calculado uma vez a partir do saldo de
     * hoje: saldo atual + o que foi gasto desde START − os créditos desde START.
     */
    private static function ensureStartOpening(ClientAdAccount $account, Collection $consumed, Collection $documents, Collection &$statements): ?float
    {
        $key = Carbon::parse(self::START)->format('Y-m');
        $stmt = $statements->get($key);

        if ($stmt && $stmt->opening_balance !== null) {
            return (float) $stmt->opening_balance;
        }

        $spentSince = collect($consumed)->filter(fn ($v, $m) => $m >= $key)->sum();
        $creditsSince = $documents->filter(fn ($d, $m) => $m >= $key)->collapse()->filter->isCredit()->sum('amount');
        $opening = round((float) $account->balance + (float) $spentSince - (float) $creditsSince, 2);

        $stmt = AdAccountStatement::updateOrCreate(
            ['client_ad_account_id' => $account->id, 'month' => self::START],
            ['organization_id' => $account->client?->organization_id, 'opening_balance' => $opening, 'opening_balance_source' => 'auto']
        );
        $statements->put($key, $stmt);

        return $opening;
    }
}

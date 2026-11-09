<?php

namespace App\Services\Billing;

use App\Models\AdStatementDelivery;
use App\Models\Client;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Avisa o cliente das notas fiscais do mês: um aviso só (WhatsApp/e-mail,
 * mensagem padrão "prestacao_contas"), mesmo que tenham subido várias notas.
 * O aviso não leva documento nem link público — só o caminho do Portal, onde
 * as notas abrem com login. Vai para quem assina "Prestação de contas" na
 * ficha do cliente.
 */
class StatementDeliveryService
{
    public const MONTHS = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    public function __construct(private NotificationDispatchService $notifier) {}

    /** @param Collection<int, \App\Models\ClientAdBillingDocument> $documents notas do envio */
    public function send(Client $client, Carbon $month, Collection $documents, ?int $userId): AdStatementDelivery
    {
        $total = (float) $documents->sum('amount');

        $delivery = AdStatementDelivery::create([
            'organization_id' => $client->organization_id,
            'client_id'       => $client->id,
            'month'           => $month->copy()->startOfMonth()->toDateString(),
            'document_ids'    => $documents->pluck('id')->values()->all(),
            'total'           => $total ?: null,
            'sent_by'         => $userId,
            'sent_at'         => now(),
        ]);

        $count = $documents->count();
        $this->notifier->send('prestacao_contas', $client, [
            'mes'            => self::monthLabel($month),
            'quantidade'     => $count . ($count === 1 ? ' nota' : ' notas'),
            'valor'          => $total > 0 ? 'R$ ' . number_format($total, 2, ',', '.') : '—',
            'link_prestacao' => $delivery->portalUrl(),
        ], null, $delivery);

        $documents->each->update(['notified_at' => now()]);

        return $delivery;
    }

    public static function monthLabel(Carbon $month): string
    {
        return self::MONTHS[$month->month] . '/' . $month->year;
    }
}

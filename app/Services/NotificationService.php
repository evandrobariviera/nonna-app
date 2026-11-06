<?php

namespace App\Services;

use App\Models\InternalNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Fan-out de notificação interna: uma linha por destinatário, não uma linha
 * compartilhada — cada pessoa marca a própria como lida/resolvida sem afetar
 * a cópia de outra (mesmo princípio do CampaignInsight, só generalizado).
 *
 * Atenção eficiente (2026-11-06): se a pessoa ainda NÃO abriu um aviso do mesmo tipo sobre o
 * mesmo item, não nasce outro — o existente é atualizado (texto mais recente, sobe pro topo)
 * e conta as repetições (repeat_count). Acaba com a rajada de "fulano comentou em X" ×5.
 */
class NotificationService
{
    /** Junta com o aviso não aberto se ele for desta janela (aviso velho esquecido não engole o novo). */
    private const COLLAPSE_WINDOW_DAYS = 7;

    /**
     * @param Collection<int, User> $users
     */
    public function notifyUsers(
        Collection $users,
        string $kind,
        string $title,
        ?string $body = null,
        ?string $link = null,
        ?Model $source = null,
        ?string $organizationId = null
    ): void {
        $now = now();
        $sourceType = $source ? class_basename($source) : null;
        $sourceId = $source?->getKey();

        // Um SELECT só pra todos os destinatários: quem já tem aviso aberto deste tipo/item.
        $pending = $sourceId === null ? collect() : InternalNotification::withoutGlobalScopes()
            ->whereIn('user_id', $users->pluck('id'))
            ->where('kind', $kind)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('status', 'novo')
            ->where('generated_at', '>=', $now->copy()->subDays(self::COLLAPSE_WINDOW_DAYS))
            ->get()
            ->keyBy('user_id');

        foreach ($users->unique('id') as $user) {
            if ($existing = $pending->get($user->id)) {
                $existing->update([
                    'title'        => $title,
                    'body'         => $body,
                    'link'         => $link ?? $existing->link,
                    'generated_at' => $now, // sobe pro topo do sino
                    'repeat_count' => ($existing->repeat_count ?? 1) + 1,
                ]);
                continue;
            }

            InternalNotification::create([
                // Chamado quase sempre a partir de um Job em background (sem middleware de
                // tenant), então o Tenantable não tem currentOrganization pra auto-preencher
                // sozinho — sem isso a notificação fica com organization_id NULL e some pro
                // destinatário (OrganizationScope filtra pela org atual em qualquer tela).
                'organization_id' => $organizationId ?? $user->organizations()->value('organizations.id'),
                'user_id'      => $user->id,
                'kind'         => $kind,
                'title'        => $title,
                'body'         => $body,
                'link'         => $link,
                'source_type'  => $sourceType,
                'source_id'    => $sourceId,
                'status'       => 'novo',
                'generated_at' => $now,
            ]);
        }
    }
}

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
 */
class NotificationService
{
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

        foreach ($users->unique('id') as $user) {
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
                'source_type'  => $source ? class_basename($source) : null,
                'source_id'    => $source?->getKey(),
                'status'       => 'novo',
                'generated_at' => $now,
            ]);
        }
    }
}

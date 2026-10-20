<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\InternalNotification;
use App\Models\Pendency;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Regras das Pendências (ver App\Models\Pendency). O controller só valida; aqui fica
 * o que precisa ser igual em qualquer lugar que crie/resolva uma pendência.
 *
 * Avisos (sino/Dashboard): quem é posto numa pendência — como quem cobra ou quem deve
 * entregar — recebe uma notificação interna (kind "pendencia", source = a pendência),
 * nunca quem fez a ação. Ao resolver/cancelar/excluir, os avisos abertos dela viram
 * "resolvido"; se quem resolveu não é quem cobra, quem cobra é avisado que chegou.
 */
class PendencyService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Cria a pendência. O cliente vem da origem quando houver (Tarefa/Projeto/
     * Planejamento) — o form só manda client_id na pendência avulsa.
     */
    public function create(array $data, ?Model $pendable, int $userId, bool $notifyActor = false): Pendency
    {
        $data = $this->normalizeOwed($data);

        $pendency = Pendency::create(array_merge($data, [
            'pendable_type' => $pendable?->getMorphClass(),
            'pendable_id'   => $pendable?->getKey(),
            'client_id'     => $pendable?->client_id ?? ($data['client_id'] ?? null),
            'owner_id'      => $data['owner_id'] ?? $userId,
            'requested_by'  => $data['requested_by'] ?? $userId,
            'status'        => 'aberta',
            'created_by'    => $userId,
        ]));

        // Automação: $userId é só o "criado por" (dono da automação) — avisa todo mundo envolvido.
        $this->notifyAssigned($pendency, $notifyActor ? 0 : $userId, notifyOwner: true, notifyOwed: true);

        return $pendency;
    }

    public function update(Pendency $pendency, array $data, int $userId): Pendency
    {
        $oldOwner = $pendency->owner_id;
        $oldOwed  = $pendency->owed_by === 'agencia' ? $pendency->owed_user_id : null;

        $pendency->update($this->normalizeOwed($data));

        $newOwed = $pendency->owed_by === 'agencia' ? $pendency->owed_user_id : null;
        $this->notifyAssigned(
            $pendency,
            $userId,
            notifyOwner: (int) $oldOwner !== (int) $pendency->owner_id,
            notifyOwed: $newOwed && (int) $oldOwed !== (int) $newOwed,
        );

        return $pendency;
    }

    /** "Cobrei" — registra mais uma cobrança feita (contador + data da última). */
    /**
     * Registra uma cobrança (quem, canal, quando). Canal "lembrete" = cobrar alguém do
     * time: o próprio App avisa a pessoa no sino.
     */
    public function nudge(Pendency $pendency, int $userId, string $channel): void
    {
        // Tudo ou nada: se o aviso falhar, a cobrança não fica registrada pela metade.
        \Illuminate\Support\Facades\DB::connection('pgsql')->transaction(fn () => $this->recordNudge($pendency, $userId, $channel));
    }

    private function recordNudge(Pendency $pendency, int $userId, string $channel): void
    {
        $pendency->nudges()->create(['user_id' => $userId, 'channel' => $channel]);

        $pendency->forceFill([
            'status'         => 'cobrada',
            'nudge_count'    => $pendency->nudge_count + 1,
            'last_nudged_at' => now(),
        ])->save();

        if ($channel === 'lembrete' && $pendency->owed_by === 'agencia' && $pendency->owed_user_id
            && (int) $pendency->owed_user_id !== $userId && ($owed = User::find($pendency->owed_user_id))) {
            $this->notify(
                collect([$owed]),
                Pendency::NOTIFICATION_KIND,
                (User::find($userId)?->name ?? 'Alguém') . ' está cobrando: ' . $pendency->title,
                $this->bodyFor($pendency),
                $this->linkFor($pendency),
                $pendency,
                $pendency->organization_id,
            );
        }
    }

    /** "Lembrar" — quem precisa apressa quem cobra (aviso no sino; não conta como cobrança). */
    public function remindOwner(Pendency $pendency, int $userId): void
    {
        if (!$pendency->owner_id || (int) $pendency->owner_id === $userId || !($owner = User::find($pendency->owner_id))) {
            return;
        }
        $this->notify(
            collect([$owner]),
            Pendency::NOTIFICATION_KIND,
            (User::find($userId)?->name ?? 'Alguém') . ' está esperando: ' . $pendency->title,
            $this->bodyFor($pendency) . ($pendency->nudge_count ? ' · cobrada ' . $pendency->nudge_count . 'x' : ' · ainda não cobrada'),
            $this->linkFor($pendency),
            $pendency,
            $pendency->organization_id,
        );
    }

    public function resolve(Pendency $pendency, int $userId, ?string $note = null): void
    {
        $pendency->forceFill([
            'status'          => 'resolvida',
            'resolved_at'     => now(),
            'resolved_by'     => $userId,
            'resolution_note' => $note ?: null,
        ])->save();

        $this->closeNotifications($pendency);

        // Quem cobra e quem precisa ficam sabendo que chegou — menos quem marcou.
        $notify = User::whereIn('id', collect([$pendency->owner_id, $pendency->requested_by])->filter()
            ->map(fn ($id) => (int) $id)->reject(fn ($id) => $id === $userId)->unique())->get();
        if ($notify->isNotEmpty()) {
            $by = User::find($userId)?->name ?? 'Alguém';
            $this->notify(
                $notify,
                Pendency::NOTIFICATION_KIND,
                'Pendência resolvida: ' . $pendency->title,
                $by . ' marcou como resolvida' . ($note ? ' — ' . $note : '.'),
                $this->linkFor($pendency),
                $pendency,
                $pendency->organization_id,
            );
        }
    }

    /**
     * Todo aviso de pendência passa por aqui. internal_notifications.title é varchar(255) e
     * o título da pendência pode ser enorme (comentário virado pendência) — corta o título
     * do aviso e manda o texto inteiro no corpo, que não tem limite.
     */
    private function notify(Collection $users, string $kind, string $title, ?string $body, ?string $link, Pendency $pendency, ?string $organizationId): void
    {
        if (mb_strlen($title) > 200) {
            $body  = $pendency->title . ($body ? "\n" . $body : '');
            $title = \Illuminate\Support\Str::limit($title, 200);
        }

        $this->notifications->notifyUsers($users, $kind, $title, $body, $link, $pendency, $organizationId);
    }

    private function bodyFor(Pendency $pendency): string
    {
        $client = $pendency->client?->displayName();
        $due    = $pendency->due_date ? ' · prazo ' . $pendency->due_date->format('d/m') : '';

        return trim(($client ? $client . ' · ' : '') . 'deve: ' . $pendency->owedName() . $due);
    }

    public function cancel(Pendency $pendency, int $userId): void
    {
        $pendency->forceFill([
            'status'      => 'cancelada',
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ])->save();

        $this->closeNotifications($pendency);
    }

    public function delete(Pendency $pendency): void
    {
        $this->closeNotifications($pendency);
        $pendency->delete();
    }

    /** Avisa quem passou a cobrar e/ou a dever a pendência — nunca quem fez a ação. */
    private function notifyAssigned(Pendency $pendency, int $actorId, bool $notifyOwner, bool $notifyOwed): void
    {
        $body = $this->bodyFor($pendency);

        $send = function (?int $userId, string $title) use ($pendency, $actorId, $body) {
            if (!$userId || $userId === $actorId || !($user = User::find($userId))) {
                return;
            }
            $this->notify(collect([$user]), Pendency::NOTIFICATION_KIND, $title, $body,
                $this->linkFor($pendency), $pendency, $pendency->organization_id);
        };

        $owedId = $pendency->owed_by === 'agencia' ? (int) $pendency->owed_user_id : null;

        if ($notifyOwed && $owedId) {
            $send($owedId, 'Você precisa entregar: ' . $pendency->title);
        }
        // Se a mesma pessoa cobra e deve, um aviso só basta.
        if ($notifyOwner && (int) $pendency->owner_id !== $owedId) {
            $send((int) $pendency->owner_id, 'Você cobra uma pendência: ' . $pendency->title);
        }
    }

    private function closeNotifications(Pendency $pendency): void
    {
        InternalNotification::where('source_type', 'Pendency')
            ->where('source_id', $pendency->id)
            ->whereIn('status', ['novo', 'lido'])
            ->update(['status' => 'resolvido']);
    }

    private function linkFor(Pendency $pendency): string
    {
        $origin = $pendency->originLink();

        return $origin ? $origin['url'] . '#pendencias' : route('pendencies.index', ['client_id' => $pendency->client_id]);
    }

    /** Volta pra aberta (ou cobrada, se já tinha sido cobrada antes). */
    public function reopen(Pendency $pendency): void
    {
        $pendency->forceFill([
            'status'          => $pendency->nudge_count > 0 ? 'cobrada' : 'aberta',
            'resolved_at'     => null,
            'resolved_by'     => null,
            'resolution_note' => null,
        ])->save();
    }

    /**
     * Dados do bloco "Pendências" de uma Tarefa/Projeto/Planejamento: abertas primeiro
     * (prazo mais próximo no topo, sem prazo depois), resolvidas/canceladas no fim.
     * Contatos do cliente pra o seletor "Deve: cliente".
     */
    public function panelData(Model $pendable): array
    {
        $pendencies = $pendable->pendencies()
            ->with(['owedContact:id,name,whatsapp,phone', 'owedUser:id,name,avatar_path,avatar_disk', 'owner:id,name,avatar_path,avatar_disk', 'requester:id,name', 'resolvedBy:id,name', 'nudges.user:id,name'])
            ->orderByRaw("CASE WHEN status IN ('aberta','cobrada') THEN 0 ELSE 1 END")
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get();

        $contacts = $pendable->client_id ? $this->clientContacts($pendable->client_id) : collect();

        return [
            'open'     => $pendencies->filter->isOpen()->values(),
            'closed'   => $pendencies->reject->isOpen()->values(),
            'contacts' => $contacts,
        ];
    }

    public function clientContacts(string $clientId): Collection
    {
        return Contact::whereHas('clients', fn ($q) => $q->where('clients.id', $clientId))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Apaga os campos de "quem deve" que não valem pro tipo escolhido. */
    private function normalizeOwed(array $data): array
    {
        if (!array_key_exists('owed_by', $data)) {
            return $data;
        }

        $data['owed_contact_id'] = $data['owed_by'] === 'cliente' ? ($data['owed_contact_id'] ?? null) : null;
        $data['owed_user_id']    = $data['owed_by'] === 'agencia' ? ($data['owed_user_id'] ?? null) : null;
        $data['owed_party']      = $data['owed_by'] === 'terceiro' ? ($data['owed_party'] ?? null) : null;

        return $data;
    }
}

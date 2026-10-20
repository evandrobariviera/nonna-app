<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Pendency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Regras das Pendências (ver App\Models\Pendency). O controller só valida; aqui fica
 * o que precisa ser igual em qualquer lugar que crie/resolva uma pendência.
 */
class PendencyService
{
    /**
     * Cria a pendência. O cliente vem da origem quando houver (Tarefa/Projeto/
     * Planejamento) — o form só manda client_id na pendência avulsa.
     */
    public function create(array $data, ?Model $pendable, int $userId): Pendency
    {
        $data = $this->normalizeOwed($data);

        return Pendency::create(array_merge($data, [
            'pendable_type' => $pendable?->getMorphClass(),
            'pendable_id'   => $pendable?->getKey(),
            'client_id'     => $pendable?->client_id ?? ($data['client_id'] ?? null),
            'owner_id'      => $data['owner_id'] ?? $userId,
            'status'        => 'aberta',
            'created_by'    => $userId,
        ]));
    }

    public function update(Pendency $pendency, array $data): Pendency
    {
        $pendency->update($this->normalizeOwed($data));

        return $pendency;
    }

    /** "Cobrei" — registra mais uma cobrança feita (contador + data da última). */
    public function nudge(Pendency $pendency): void
    {
        $pendency->forceFill([
            'status'         => 'cobrada',
            'nudge_count'    => $pendency->nudge_count + 1,
            'last_nudged_at' => now(),
        ])->save();
    }

    public function resolve(Pendency $pendency, int $userId, ?string $note = null): void
    {
        $pendency->forceFill([
            'status'          => 'resolvida',
            'resolved_at'     => now(),
            'resolved_by'     => $userId,
            'resolution_note' => $note ?: null,
        ])->save();
    }

    public function cancel(Pendency $pendency, int $userId): void
    {
        $pendency->forceFill([
            'status'      => 'cancelada',
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ])->save();
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
            ->with(['owedContact:id,name', 'owedUser:id,name,avatar_path,avatar_disk', 'owner:id,name,avatar_path,avatar_disk', 'resolvedBy:id,name'])
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

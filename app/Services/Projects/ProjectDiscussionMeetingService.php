<?php

namespace App\Services\Projects;

use App\Models\MacroPlan;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reunião de discussão de campanhas/projetos (fluxo 2026-10-21): depois da leitura do
 * planejamento, o responsável escolhe a pauta (itens do planejamento) e a equipe de cada
 * item. Sai UMA reunião (agendamento agrupado) com cada item como bloco da pauta; a equipe
 * fica gravada no item (Project::members), não só na reunião.
 *
 * Participantes = equipes dos itens + responsável do cliente + quem agendou.
 */
class ProjectDiscussionMeetingService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * @param array $meetingData scheduled_at, duration_minutes, modality, online_link, location
     * @param array<int, array{id:string, team:array<int,int>}> $items na ordem da pauta
     */
    public function schedule(MacroPlan $macroplan, array $meetingData, array $items, int $userId): Meeting
    {
        $projects = Project::where('macro_plan_id', $macroplan->id)
            ->whereIn('id', array_column($items, 'id'))
            ->get()
            ->keyBy('id');
        $items = array_values(array_filter($items, fn ($item) => $projects->has($item['id'])));
        if (!$items) {
            throw new \InvalidArgumentException('Escolha ao menos uma campanha ou projeto pra pauta.');
        }

        $leadId = $macroplan->client?->creative_lead_id;
        $teamIds = collect($items)->flatMap(fn ($item) => $item['team'] ?? []);
        $users = User::whereIn('id', $teamIds->push($leadId)->filter()->unique())->get(['id', 'name'])->keyBy('id');

        $meeting = DB::connection('pgsql')->transaction(function () use ($macroplan, $meetingData, $items, $projects, $users, $leadId, $userId) {
            // Responsável do cliente + quem agendou + as equipes de todos os itens.
            $participantIds = collect([$leadId, $userId]);
            foreach ($items as $item) {
                $team = collect($item['team'] ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => $users->has($id))->unique()->values();
                $projects[$item['id']]->members()->sync($team->all());
                $participantIds = $participantIds->merge($team);
            }

            $first = $projects[$items[0]['id']];
            $meeting = Meeting::create([
                'organization_id'  => $macroplan->organization_id,
                'title'            => count($items) === 1
                    ? 'Discussão — ' . $first->title
                    : 'Discussão de campanhas e projetos — ' . ($macroplan->client?->displayName() ?? $macroplan->title),
                'type'             => 'alinhamento_projeto',
                'modality'         => $meetingData['modality'],
                'status'           => 'agendada',
                'client_id'        => $macroplan->client_id,
                'macro_plan_id'    => $macroplan->id,
                'organized_by'     => $userId,
                'created_by'       => $userId,
                'scheduled_at'     => $meetingData['scheduled_at'],
                'duration_minutes' => $meetingData['duration_minutes'] ?? null,
                'online_link'      => $meetingData['online_link'] ?? null,
                'location'         => $meetingData['location'] ?? null,
            ]);

            // A pauta são os itens ligados (Meeting::projects) — o campo `agenda` fica livre
            // pra observações de quem agenda.
            $meeting->projects()->sync(collect($items)->mapWithKeys(fn ($item, $i) => [$item['id'] => ['position' => $i]])->all());
            $meeting->participants()->sync($participantIds->filter()->unique()->values()->all());

            return $meeting;
        });

        $this->notifyParticipants($meeting, $userId);

        return $meeting;
    }

    private function notifyParticipants(Meeting $meeting, int $actorId): void
    {
        $recipients = $meeting->participants()->where('users.id', '!=', $actorId)->get();
        if ($recipients->isEmpty()) {
            return;
        }

        $this->notifications->notifyUsers(
            $recipients,
            'reuniao_lembrete',
            'Reunião de discussão: ' . $meeting->title,
            $meeting->scheduled_at->format('d/m H:i') . ' · leia o documento de cada item da pauta antes',
            route('meetings.show', $meeting),
            $meeting,
            $meeting->organization_id,
        );
    }

    /**
     * Reunião de discussão finalizada → os itens da pauta que ainda estavam em
     * planejamento começam a execução (decisão do Evandro, 2026-10-21).
     */
    public static function startDiscussedProjects(Meeting $meeting): void
    {
        $meeting->projects()
            ->where('projects.status', 'em_planejamento')
            ->get()
            ->each(fn (Project $project) => $project->update(['status' => 'em_execucao']));
    }

    /**
     * Ponto de partida do formulário de agendamento: cada item do planejamento com a equipe
     * atual (ou o responsável do cliente, se ainda não tem equipe) e se já foi pra reunião.
     *
     * @return Collection<int, array>
     */
    public static function formItems(MacroPlan $macroplan): Collection
    {
        $leadId = $macroplan->client?->creative_lead_id;

        return $macroplan->projects
            ->reject(fn (Project $p) => in_array($p->status, ['concluido', 'cancelado'], true))
            ->values()
            ->map(fn (Project $p) => [
                'id'      => $p->id,
                'title'   => $p->title,
                'type'    => $p->typeLabel(),
                'checked' => $p->discussionMeetings->isEmpty(),
                'team'    => $p->members->isNotEmpty() ? $p->members->pluck('id')->all() : array_values(array_filter([$leadId])),
            ]);
    }
}

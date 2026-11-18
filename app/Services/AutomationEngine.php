<?php

namespace App\Services;

use App\Jobs\AutomationJob;
use App\Models\Automation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AutomationEngine
{
    /**
     * Avalia quais automações devem disparar para um dado gatilho e entidade.
     * Chamado pelos Observers quando algo muda no sistema.
     */
    public static function evaluate(string $triggerType, Model $entity, array $changeData = []): void
    {
        $entityType = self::resolveEntityType($entity);
        if (!$entityType) {
            return;
        }

        // Só as automações da organização da entidade: no Portal (e em jobs) não há
        // currentOrganization, e o escopo global deixaria passar as de todas.
        $automations = Automation::where('entity_type', $entityType)
            ->where('is_active', true)
            ->when($entity->getAttribute('organization_id'), fn ($q, $orgId) => $q->where('organization_id', $orgId))
            ->get();

        foreach ($automations as $automation) {
            if ($automation->matches($triggerType, $changeData, $entity)) {
                AutomationJob::dispatch($automation, $entityType, $entity->getKey(), $changeData);
            }
        }
    }

    private static function resolveEntityType(Model $entity): ?string
    {
        return match (true) {
            // Ticket é a mesma tabela/model de Tarefa (Task.is_ticket) — entidades diferentes
            // pro motor de Automações, mesmo model por baixo.
            $entity instanceof \App\Models\Task        => $entity->is_ticket ? 'ticket' : 'task',
            $entity instanceof \App\Models\Project     => 'project',
            $entity instanceof \App\Models\AdCampaign  => 'campaign',
            $entity instanceof \App\Models\Opportunity => 'opportunity',
            $entity instanceof \App\Models\Meeting     => 'meeting',
            $entity instanceof \App\Models\MacroPlan   => 'macro_plan',
            default                                     => null,
        };
    }
}

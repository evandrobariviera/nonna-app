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

        $automations = Automation::where('entity_type', $entityType)
            ->where('is_active', true)
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
            $entity instanceof \App\Models\Task        => 'task',
            $entity instanceof \App\Models\Project     => 'project',
            $entity instanceof \App\Models\AdCampaign  => 'campaign',
            default                                     => null,
        };
    }
}

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

        // Demonstração (Portal de exemplo) nunca dispara automação — a tabela automations
        // não tem organização, então as regras da agência valeriam pros dados fictícios.
        if (self::isDemoEntity($entity)) {
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

    /** @var array<string, bool> organização => é demo (cache por processo) */
    private static array $demoOrgs = [];

    public static function isDemoEntity(Model $entity): bool
    {
        $orgId = $entity->getAttribute('organization_id');
        if (!$orgId) {
            return false;
        }

        return self::$demoOrgs[$orgId] ??= \App\Models\Organization::whereKey($orgId)->value('status') === 'demo';
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

<?php

namespace App\Services;

use App\Models\Task;
use App\Models\Project;
use App\Models\AdCampaign;
use Illuminate\Database\Eloquent\Model;

class ContextResolver
{
    public static function for(Model $entity): array
    {
        return match (true) {
            $entity instanceof Task       => self::forTask($entity),
            $entity instanceof Project    => self::forProject($entity),
            $entity instanceof AdCampaign => self::forCampaign($entity),
            default                       => [],
        };
    }

    public static function forTask(Task $task): array
    {
        $task->loadMissing(['project', 'client', 'executor']);

        $context = [
            'task_id'          => $task->id,
            'task_title'       => $task->title ?? '',
            'task_description' => $task->description ?? '',
            'task_type'        => $task->typeLabel(),
            'task_status'      => $task->statusLabel(),
            'task_situation'   => $task->situationLabel(),
            'task_destination' => $task->destinationLabel(),
            'task_due_date'    => $task->due_date?->format('d/m/Y') ?? '',
        ];

        if ($task->client) {
            $context['client_name']    = $task->client->name ?? '';
            $context['client_segment'] = $task->client->segment ?? '';
        }

        if ($task->project) {
            $context['project_name']  = $task->project->name ?? '';
            $context['project_brief'] = $task->project->description ?? '';
        }

        if ($task->executor) {
            $context['executor_name'] = $task->executor->name ?? '';
        }

        return $context;
    }

    public static function forProject(Project $project): array
    {
        $project->loadMissing(['client', 'macroPlan']);

        $context = [
            'project_id'          => $project->id,
            'project_name'        => $project->name ?? '',
            'project_description' => $project->description ?? '',
        ];

        if ($project->client) {
            $context['client_name']    = $project->client->name ?? '';
            $context['client_segment'] = $project->client->segment ?? '';
        }

        if ($project->macroPlan) {
            $context['macro_plan_name'] = $project->macroPlan->name ?? '';
        }

        return $context;
    }

    public static function forCampaign(AdCampaign $campaign): array
    {
        $campaign->loadMissing(['client']);

        return [
            'campaign_id'      => $campaign->id,
            'campaign_name'    => $campaign->name ?? '',
            'campaign_status'  => $campaign->status ?? '',
            'campaign_objective' => $campaign->objective ?? '',
            'client_name'      => $campaign->client?->name ?? '',
        ];
    }
}

<?php

namespace App\Services;

use App\Jobs\DispatchWebhookJob;
use App\Models\Organization;

class WebhookDispatcher
{
    public static function fire(string $event, array $payload, ?Organization $org = null): void
    {
        $organization = $org ?? (app()->has('currentOrganization') ? app('currentOrganization') : null);

        if (!$organization) return;

        $integration = $organization->integration('n8n');
        if (!$integration) return;

        $baseUrl    = data_get($integration->settings, 'base_url');
        $webhookPath = data_get($integration->settings, "webhooks.{$event}");

        if (!$baseUrl || !$webhookPath) return;

        $url = rtrim($baseUrl, '/') . '/' . ltrim($webhookPath, '/');

        DispatchWebhookJob::dispatch($url, $event, $payload, $organization->id);
    }
}

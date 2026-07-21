<?php

namespace App\Http\Controllers;

use App\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $org = app('currentOrganization');

        $data = $request->validate([
            'templates'              => ['nullable', 'array'],
            'templates.*.*.subject'  => ['nullable', 'string', 'max:200'],
            'templates.*.*.body'     => ['nullable', 'string', 'max:4000'],
        ]);

        foreach (NotificationTemplate::$types as $type => $label) {
            foreach (NotificationTemplate::$channels as $channel => $channelLabel) {
                $subject = $data['templates'][$type][$channel]['subject'] ?? null;
                $body    = $data['templates'][$type][$channel]['body'] ?? null;

                if (blank($subject) && blank($body)) {
                    continue;
                }

                NotificationTemplate::updateOrCreate(
                    ['organization_id' => $org->id, 'type' => $type, 'channel' => $channel],
                    ['subject' => $subject, 'body' => $body]
                );
            }
        }

        return back()->with('success', 'Mensagens padrão salvas.')->with('tab', 'mensagens');
    }
}

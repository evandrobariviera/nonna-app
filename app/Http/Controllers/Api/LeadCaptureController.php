<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Leads\LeadCaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadCaptureController extends Controller
{
    // POST /api/leads/captura
    // n8n manda aqui, sempre no mesmo formato, não importa se a origem foi
    // site (GTM/UTM), Facebook/Instagram Lead Ads ou WhatsApp direto.
    public function store(Request $request, LeadCaptureService $service): JsonResponse
    {
        $data = $request->validate([
            'source_channel'    => ['required', 'string', 'max:20'],
            'source_identifier' => ['required', 'string', 'max:150'],
            'name'              => ['nullable', 'string', 'max:150'],
            'email'             => ['nullable', 'email', 'max:150', 'required_without:phone'],
            'phone'             => ['nullable', 'string', 'max:30', 'required_without:email'],
            'form_name'         => ['nullable', 'string', 'max:150'],
            'landing_page_url'  => ['nullable', 'string', 'max:2048'],
            'utm_source'        => ['nullable', 'string', 'max:150'],
            'utm_medium'        => ['nullable', 'string', 'max:150'],
            'utm_campaign'      => ['nullable', 'string', 'max:150'],
            'utm_content'       => ['nullable', 'string', 'max:150'],
            'utm_term'          => ['nullable', 'string', 'max:150'],
            'fbclid'            => ['nullable', 'string', 'max:255'],
            'gclid'             => ['nullable', 'string', 'max:255'],
            'ctwa_clid'         => ['nullable', 'string', 'max:255'],
            'event_id'          => ['nullable', 'string', 'max:150'],
            'city'              => ['nullable', 'string', 'max:100'],
            'state'             => ['nullable', 'string', 'max:2'],
            'received_at'       => ['nullable', 'date'],
            'raw_payload'       => ['nullable', 'array'],
        ]);

        $org = app('currentOrganization');

        $result = $service->capture($org, $data);

        $status = $result['client_id'] ? 201 : 202;

        return response()->json($result, $status);
    }
}

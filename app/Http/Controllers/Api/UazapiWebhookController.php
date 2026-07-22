<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\UazapiMessageIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UazapiWebhookController extends Controller
{
    public function __invoke(Request $request, UazapiMessageIngestor $ingestor): JsonResponse
    {
        try {
            $ingestor->ingest($request->all());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['ok' => true]);
    }
}

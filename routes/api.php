<?php

use App\Http\Controllers\Api\AdAccountController;
use App\Http\Controllers\Api\ClickupImportController;
use App\Http\Controllers\Api\ClickupMacroPlanImportController;
use App\Http\Controllers\Api\ClickupProjectImportController;
use App\Http\Controllers\Api\IntegrationCredentialController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Middleware\SetApiTenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Interna — consumida pelo n8n e futuramente por agentes de IA
|--------------------------------------------------------------------------
| Autenticação: Bearer token via Laravel Sanctum (token vinculado à Organization)
| Toda request resolve o tenant pelo token — não usa subdomínio
|--------------------------------------------------------------------------
*/

// ── ClickUp Import — n8n envia dados para importar (sem Sanctum, autenticado por header X-Import-Secret)
Route::post('/clickup/import',            [ClickupImportController::class,         'import']);
Route::post('/clickup/import-macroplans', [ClickupMacroPlanImportController::class, 'import']);
Route::post('/clickup/import-projects',   [ClickupProjectImportController::class,   'import']);
Route::get('/clickup/project-lists',      [ClickupProjectImportController::class,   'lists']);

Route::middleware(['auth:sanctum', SetApiTenant::class])->group(function () {

    // ── Contas de anúncios ──
    // A sincronização diária roda dentro do App (campaigns:sync-ad-platforms).
    // Endpoint disponível como via alternativa/externa (n8n ou outra ferramenta).
    Route::get('/ad-accounts', [AdAccountController::class, 'index']);

    // ── Credenciais de integração ──
    Route::get('/integrations/{provider}', [IntegrationCredentialController::class, 'show']);

    // ── Sync de dados (endpoint HTTP alternativo — a rotina principal usa AdDataUpserter direto) ──
    Route::prefix('sync')->group(function () {
        Route::post('/campaigns', [SyncController::class, 'campaigns']);
        Route::post('/snapshots', [SyncController::class, 'snapshots']);
    });

});

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

Route::middleware(['auth:sanctum', SetApiTenant::class])->group(function () {

    // ── Contas de anúncios ──
    // n8n chama para saber quais contas sincronizar
    Route::get('/ad-accounts', [AdAccountController::class, 'index']);

    // ── Credenciais de integração ──
    // n8n busca o token/credencial da organização para chamar a API externa diretamente
    Route::get('/integrations/{provider}', [IntegrationCredentialController::class, 'show']);

    // ── Sync de dados do n8n ──
    Route::prefix('sync')->group(function () {
        Route::post('/campaigns', [SyncController::class, 'campaigns']);
        Route::post('/snapshots', [SyncController::class, 'snapshots']);
    });

});

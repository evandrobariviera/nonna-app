<?php

use App\Http\Controllers\Api\AdAccountController;
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

Route::middleware(['auth:sanctum', SetApiTenant::class])->group(function () {

    // ── Contas de anúncios ──
    // n8n chama para saber quais contas sincronizar
    Route::get('/ad-accounts', [AdAccountController::class, 'index']);

    // ── Sync de dados do n8n ──
    Route::prefix('sync')->group(function () {
        Route::post('/campaigns', [SyncController::class, 'campaigns']);
        Route::post('/snapshots', [SyncController::class, 'snapshots']);
    });

});

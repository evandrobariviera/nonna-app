<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientCredentialController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicRegistrationController;
use App\Models\ClickupTaskClient;
use Illuminate\Support\Facades\Route;

// ── Página de boas-vindas ──
Route::get('/', fn () => view('welcome'));

// ── Dashboard ──
Route::get('/dashboard', function () {
    $clients = ClickupTaskClient::all();
    return view('dashboard', compact('clients'));
})->middleware(['auth', 'verified'])->name('dashboard');

// ── CRM: Clientes (autenticado) ──
Route::middleware(['auth', 'verified'])->group(function () {

    Route::resource('clientes', ClientController::class)->parameters([
        'clientes' => 'client',
    ])->names([
        'index'   => 'clients.index',
        'create'  => 'clients.create',
        'store'   => 'clients.store',
        'show'    => 'clients.show',
        'edit'    => 'clients.edit',
        'update'  => 'clients.update',
        'destroy' => 'clients.destroy',
    ]);

    Route::post('/clientes/{client}/gerar-link', [ClientController::class, 'generateToken'])
        ->name('clients.generate-token');

    // ── Credenciais do cliente (senhas) ──
    Route::post('/clientes/{client}/credenciais', [ClientCredentialController::class, 'store'])
        ->name('clients.credentials.store');
    Route::patch('/clientes/{client}/credenciais/{credential}', [ClientCredentialController::class, 'update'])
        ->name('clients.credentials.update');
    Route::delete('/clientes/{client}/credenciais/{credential}', [ClientCredentialController::class, 'destroy'])
        ->name('clients.credentials.destroy');

    // ── CRM: Contatos ──
    Route::resource('contatos', ContactController::class)->parameters([
        'contatos' => 'contact',
    ])->names([
        'index'  => 'contacts.index',
        'create' => 'contacts.create',
        'store'  => 'contacts.store',
        'show'   => 'contacts.show',
        'edit'   => 'contacts.edit',
        'update' => 'contacts.update',
    ])->except(['destroy']);

    // ── CRM: Oportunidades ──
    Route::resource('oportunidades', OpportunityController::class)->parameters([
        'oportunidades' => 'opportunity',
    ])->names([
        'index'  => 'opportunities.index',
        'create' => 'opportunities.create',
        'store'  => 'opportunities.store',
        'show'   => 'opportunities.show',
        'edit'   => 'opportunities.edit',
        'update' => 'opportunities.update',
    ])->except(['destroy']);

    Route::patch('/oportunidades/{opportunity}/stage', [OpportunityController::class, 'updateStage'])
        ->name('opportunities.update-stage');

    Route::post('/oportunidades/{opportunity}/ganhar', [OpportunityController::class, 'win'])
        ->name('opportunities.win');

    Route::post('/oportunidades/{opportunity}/perder', [OpportunityController::class, 'lose'])
        ->name('opportunities.lose');

});

// ── Cadastro público (sem autenticação) ──
Route::get('/cadastro/{token}', [PublicRegistrationController::class, 'show'])
    ->name('clients.register');

Route::post('/cadastro/{token}', [PublicRegistrationController::class, 'submit'])
    ->name('clients.register.submit');

// ── Perfil ──
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

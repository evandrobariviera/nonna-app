<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Models\ClickupTaskClient; // Importa o nosso Model de Clientes

Route::get('/dashboard', function () {
    // 🔍 O Eloquent faz a mágica: busca todos os clientes sincronizados da Hetzner!
    $clients = ClickupTaskClient::all(); 

    // Envia os clientes para a página do Dashboard
    return view('dashboard', compact('clients'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

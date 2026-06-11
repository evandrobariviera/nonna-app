<?php

use App\Http\Controllers\ClientAdAccountController;
use App\Http\Controllers\FilaController;
use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientCredentialController;
use App\Http\Controllers\ClientDiagnosticController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DiagnosticCompetitorController;
use App\Http\Controllers\DiagnosticPersonaController;
use App\Http\Controllers\MacroPlanController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TicketController;
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

    // ── Contatos vinculados ao cliente ──
    Route::post('/clientes/{client}/contatos/vincular', [ClientContactController::class, 'link'])
        ->name('clients.contacts.link');
    Route::post('/clientes/{client}/contatos/novo', [ClientContactController::class, 'storeAndLink'])
        ->name('clients.contacts.store-and-link');
    Route::patch('/clientes/{client}/contatos/{contact}/vinculo', [ClientContactController::class, 'updatePivot'])
        ->name('clients.contacts.update-pivot');
    Route::delete('/clientes/{client}/contatos/{contact}/desvincular', [ClientContactController::class, 'unlink'])
        ->name('clients.contacts.unlink');

    // ── Contas de Anúncios do cliente ──
    Route::post('/clientes/{client}/contas-anuncios', [ClientAdAccountController::class, 'store'])
        ->name('clients.ad-accounts.store');
    Route::patch('/clientes/{client}/contas-anuncios/{adAccount}', [ClientAdAccountController::class, 'update'])
        ->name('clients.ad-accounts.update');
    Route::delete('/clientes/{client}/contas-anuncios/{adAccount}', [ClientAdAccountController::class, 'destroy'])
        ->name('clients.ad-accounts.destroy');

    // ── Diagnósticos do cliente ──
    Route::post('/clientes/{client}/diagnosticos', [ClientDiagnosticController::class, 'store'])
        ->name('clients.diagnostics.store');
    Route::get('/clientes/{client}/diagnosticos/{diagnostic}/editar', [ClientDiagnosticController::class, 'edit'])
        ->name('clients.diagnostics.edit');
    Route::patch('/clientes/{client}/diagnosticos/{diagnostic}', [ClientDiagnosticController::class, 'update'])
        ->name('clients.diagnostics.update');
    Route::post('/clientes/{client}/diagnosticos/{diagnostic}/concluir', [ClientDiagnosticController::class, 'complete'])
        ->name('clients.diagnostics.complete');
    Route::post('/clientes/{client}/diagnosticos/{diagnostic}/reabrir', [ClientDiagnosticController::class, 'reopen'])
        ->name('clients.diagnostics.reopen');
    Route::delete('/clientes/{client}/diagnosticos/{diagnostic}', [ClientDiagnosticController::class, 'destroy'])
        ->name('clients.diagnostics.destroy');

    // ── Concorrentes do diagnóstico ──
    Route::post('/clientes/{client}/diagnosticos/{diagnostic}/concorrentes', [DiagnosticCompetitorController::class, 'store'])
        ->name('diagnostics.competitors.store');
    Route::patch('/clientes/{client}/diagnosticos/{diagnostic}/concorrentes/{competitor}', [DiagnosticCompetitorController::class, 'update'])
        ->name('diagnostics.competitors.update');
    Route::delete('/clientes/{client}/diagnosticos/{diagnostic}/concorrentes/{competitor}', [DiagnosticCompetitorController::class, 'destroy'])
        ->name('diagnostics.competitors.destroy');

    // ── Personas do diagnóstico ──
    Route::post('/clientes/{client}/diagnosticos/{diagnostic}/personas', [DiagnosticPersonaController::class, 'store'])
        ->name('diagnostics.personas.store');
    Route::patch('/clientes/{client}/diagnosticos/{diagnostic}/personas/{persona}', [DiagnosticPersonaController::class, 'update'])
        ->name('diagnostics.personas.update');
    Route::delete('/clientes/{client}/diagnosticos/{diagnostic}/personas/{persona}', [DiagnosticPersonaController::class, 'destroy'])
        ->name('diagnostics.personas.destroy');

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

    // ── Agenda (Reuniões) ──
    Route::resource('agenda', MeetingController::class)->parameters([
        'agenda' => 'meeting',
    ])->names([
        'index'   => 'meetings.index',
        'create'  => 'meetings.create',
        'store'   => 'meetings.store',
        'show'    => 'meetings.show',
        'edit'    => 'meetings.edit',
        'update'  => 'meetings.update',
        'destroy' => 'meetings.destroy',
    ]);

    Route::patch('/agenda/{meeting}/status', [MeetingController::class, 'updateStatus'])
        ->name('meetings.update-status');

    // ── Macroplanejamentos ──
    Route::get('/planejamentos', [MacroPlanController::class, 'index'])
        ->name('macroplans.index');
    Route::get('/planejamentos/novo', [MacroPlanController::class, 'create'])
        ->name('macroplans.create');
    Route::post('/planejamentos', [MacroPlanController::class, 'store'])
        ->name('macroplans.store');
    Route::get('/planejamentos/{macroplan}/editar', [MacroPlanController::class, 'edit'])
        ->name('macroplans.edit');
    Route::patch('/planejamentos/{macroplan}', [MacroPlanController::class, 'update'])
        ->name('macroplans.update');
    Route::delete('/planejamentos/{macroplan}', [MacroPlanController::class, 'destroy'])
        ->name('macroplans.destroy');

    // ── Filas (backlog aguardando sprint) ──
    Route::get('/filas', [FilaController::class, 'index'])
        ->name('fila.index');

    // ── Lista global de tarefas ──
    Route::get('/tarefas', [TaskController::class, 'index'])
        ->name('tasks.index');

    // ── Detalhe da tarefa ──
    Route::get('/tarefas/{task}', [TaskController::class, 'show'])
        ->name('tasks.show');
    Route::patch('/tarefas/{task}', [TaskController::class, 'updateInline'])
        ->name('tasks.update-inline');

    // ── Anexos da tarefa ──
    Route::post('/tarefas/{task}/anexos', [TaskAttachmentController::class, 'store'])
        ->name('task-attachments.store');
    Route::delete('/tarefas/{task}/anexos/{attachment}', [TaskAttachmentController::class, 'destroy'])
        ->name('task-attachments.destroy');

    // ── Comentários da tarefa ──
    Route::post('/tarefas/{task}/comentarios', [TaskCommentController::class, 'store'])
        ->name('task-comments.store');
    Route::delete('/tarefas/{task}/comentarios/{comment}', [TaskCommentController::class, 'destroy'])
        ->name('task-comments.destroy');

    // ── Dashboard global de projetos ──
    Route::get('/projetos', [ProjectController::class, 'dashboard'])
        ->name('projects.dashboard');

    // ── Projetos dentro do macroplanejamento ──
    Route::get('/planejamentos/{macroplan}/projetos/{project}', [ProjectController::class, 'show'])
        ->name('macroplans.projects.show');
    Route::post('/planejamentos/{macroplan}/projetos', [ProjectController::class, 'store'])
        ->name('macroplans.projects.store');
    Route::patch('/planejamentos/{macroplan}/projetos/{project}', [ProjectController::class, 'update'])
        ->name('macroplans.projects.update');
    Route::delete('/planejamentos/{macroplan}/projetos/{project}', [ProjectController::class, 'destroy'])
        ->name('macroplans.projects.destroy');

    // ── Sprints ──
    Route::get('/sprints', [SprintController::class, 'index'])->name('sprints.index');
    Route::get('/sprints/nova', [SprintController::class, 'create'])->name('sprints.create');
    Route::post('/sprints', [SprintController::class, 'store'])->name('sprints.store');
    Route::get('/sprints/{sprint}', [SprintController::class, 'show'])->name('sprints.show');
    Route::patch('/sprints/{sprint}', [SprintController::class, 'update'])->name('sprints.update');
    Route::post('/sprints/{sprint}/travar', [SprintController::class, 'lock'])->name('sprints.lock');
    Route::post('/sprints/{sprint}/reabrir', [SprintController::class, 'unlock'])->name('sprints.unlock');
    Route::post('/sprints/{sprint}/encerrar', [SprintController::class, 'close'])->name('sprints.close');
    Route::post('/sprints/{sprint}/tarefas/{task}', [SprintController::class, 'addTask'])->name('sprints.add-task');
    Route::delete('/sprints/{sprint}/tarefas/{task}', [SprintController::class, 'removeTask'])->name('sprints.remove-task');
    Route::delete('/sprints/{sprint}', [SprintController::class, 'destroy'])->name('sprints.destroy');

    // ── Tickets (tarefas avulsas) ──
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/novo', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::patch('/tickets/{task}/status', [TicketController::class, 'updateStatus'])->name('tickets.update-status');
    Route::delete('/tickets/{task}', [TicketController::class, 'destroy'])->name('tickets.destroy');

    // ── Tarefas dentro de um projeto ──
    Route::post('/planejamentos/{macroplan}/projetos/{project}/tarefas', [TaskController::class, 'store'])
        ->name('tasks.store');
    Route::patch('/planejamentos/{macroplan}/projetos/{project}/tarefas/{task}', [TaskController::class, 'update'])
        ->name('tasks.update');
    Route::patch('/planejamentos/{macroplan}/projetos/{project}/tarefas/{task}/status', [TaskController::class, 'updateStatus'])
        ->name('tasks.update-status');
    Route::delete('/planejamentos/{macroplan}/projetos/{project}/tarefas/{task}', [TaskController::class, 'destroy'])
        ->name('tasks.destroy');

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

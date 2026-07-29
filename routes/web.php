<?php

use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboard;
use App\Http\Controllers\SuperAdmin\OrganizationController as SuperAdminOrgs;
use App\Http\Controllers\Portal\DashboardController as PortalDashboard;
use App\Http\Controllers\Portal\ProjectController as PortalProjects;
use App\Http\Controllers\Portal\AccountController as PortalAccount;
use App\Http\Controllers\Portal\BillingDocumentController as PortalBilling;
use App\Http\Controllers\Portal\CampaignController as PortalCampaigns;
use App\Http\Controllers\Portal\ServiceDiagnosticController as PortalServiceDiagnostics;
use App\Http\Controllers\Portal\MeetingController as PortalMeetings;
use App\Http\Controllers\Portal\TicketController as PortalTickets;
use App\Http\Controllers\Portal\ApprovalController as PortalApprovals;
use App\Http\Controllers\Portal\TaskCommentController as PortalTaskComments;
use App\Http\Controllers\Portal\Auth\AuthenticatedSessionController as PortalSession;
use App\Http\Controllers\Portal\ClientContextController as PortalClientContext;
use App\Http\Controllers\ClientPortalAccessController;
use App\Http\Controllers\ClientAdAccountController;
use App\Http\Controllers\ClientAdBillingDocumentController;
use App\Http\Controllers\FilaController;
use App\Http\Controllers\OrganizationIntegrationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientCredentialController;
use App\Http\Controllers\ClientLinkController;
use App\Http\Controllers\BrandDossierController;
use App\Http\Controllers\ClientAdBudgetController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\OrcamentoController;
use App\Http\Controllers\CampaignInsightController;
use App\Http\Controllers\CampaignLogController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractAttachmentController;
use App\Http\Controllers\FinancialCategoryController;
use App\Http\Controllers\FinancialDashboardController;
use App\Http\Controllers\FinancialTransactionController;
use App\Http\Controllers\DossierCompetitorController;
use App\Http\Controllers\DossierPersonaController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\MacroPlanController;
use App\Http\Controllers\MacroPlanAttachmentController;
use App\Http\Controllers\MacroPlanImportController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\TaskApprovalController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\VisionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicRegistrationController;
use Illuminate\Support\Facades\Route;

// ── Raiz: redireciona autenticados pro dashboard, demais pro login ──
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// ── Dashboard ──
Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

// ── CRM: Clientes (autenticado) ──
Route::middleware(['auth', 'verified', 'not-client'])->group(function () {

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

    Route::get('/clientes/{client}/preview', [ClientController::class, 'preview'])
        ->name('clients.preview');

    Route::patch('/clientes/{client}/status', [ClientController::class, 'updateStatus'])
        ->name('clients.update-status');

    Route::post('/clientes/{client}/gerar-link', [ClientController::class, 'generateToken'])
        ->name('clients.generate-token');

    // ── Diagnóstico de Atendimento (WhatsApp/CRM) — visão por cliente/número ──
    Route::get('/atendimento', [\App\Http\Controllers\ServiceDiagnosticController::class, 'index'])
        ->name('service-diagnostics.index');
    Route::get('/atendimento/{integration}', [\App\Http\Controllers\ServiceDiagnosticController::class, 'integration'])
        ->name('service-diagnostics.integration');
    Route::post('/atendimento/{integration}/gerar', [\App\Http\Controllers\ServiceDiagnosticController::class, 'generate'])
        ->name('service-diagnostics.generate');
    Route::get('/atendimento/{integration}/diagnosticos/{diagnostic}', [\App\Http\Controllers\ServiceDiagnosticController::class, 'show'])
        ->name('service-diagnostics.show');
    Route::post('/atendimento/{integration}/diagnosticos/{diagnostic}/publicar', [\App\Http\Controllers\ServiceDiagnosticController::class, 'publish'])
        ->name('service-diagnostics.publish');

    // ── Credenciais do cliente (senhas) ──
    Route::post('/clientes/{client}/credenciais', [ClientCredentialController::class, 'store'])
        ->name('clients.credentials.store');
    Route::patch('/clientes/{client}/credenciais/{credential}', [ClientCredentialController::class, 'update'])
        ->name('clients.credentials.update');
    Route::delete('/clientes/{client}/credenciais/{credential}', [ClientCredentialController::class, 'destroy'])
        ->name('clients.credentials.destroy');

    // ── Números de Atendimento do cliente (client_integrations - uazapi/CRM) ──
    Route::post('/clientes/{client}/atendimento', [\App\Http\Controllers\ClientIntegrationController::class, 'store'])
        ->name('clients.integrations.store');
    Route::patch('/clientes/{client}/atendimento/{integration}', [\App\Http\Controllers\ClientIntegrationController::class, 'update'])
        ->name('clients.integrations.update');
    Route::delete('/clientes/{client}/atendimento/{integration}', [\App\Http\Controllers\ClientIntegrationController::class, 'destroy'])
        ->name('clients.integrations.destroy');

    // ── Links do cliente (Drive, contrato, briefing, etc.) ──
    Route::post('/clientes/{client}/links', [ClientLinkController::class, 'store'])
        ->name('clients.links.store');
    Route::delete('/clientes/{client}/links/{link}', [ClientLinkController::class, 'destroy'])
        ->name('clients.links.destroy');

    // ── Contatos vinculados ao cliente ──
    Route::post('/clientes/{client}/contatos/vincular', [ClientContactController::class, 'link'])
        ->name('clients.contacts.link');
    Route::post('/clientes/{client}/contatos/novo', [ClientContactController::class, 'storeAndLink'])
        ->name('clients.contacts.store-and-link');
    Route::patch('/clientes/{client}/contatos/{contact}/vinculo', [ClientContactController::class, 'updatePivot'])
        ->name('clients.contacts.update-pivot');
    Route::delete('/clientes/{client}/contatos/{contact}/desvincular', [ClientContactController::class, 'unlink'])
        ->name('clients.contacts.unlink');
    Route::put('/clientes/{client}/contatos/{contact}/comunicacoes', [ClientContactController::class, 'updateSubscriptions'])
        ->name('clients.contacts.subscriptions.update');

    // ── Contas de Anúncios do cliente ──
    Route::post('/clientes/{client}/contas-anuncios', [ClientAdAccountController::class, 'store'])
        ->name('clients.ad-accounts.store');
    Route::patch('/clientes/{client}/contas-anuncios/{adAccount}', [ClientAdAccountController::class, 'update'])
        ->name('clients.ad-accounts.update');
    Route::delete('/clientes/{client}/contas-anuncios/{adAccount}', [ClientAdAccountController::class, 'destroy'])
        ->name('clients.ad-accounts.destroy');

    // ── Boletos/PIX da conta de anúncios (Orçamentos) ──
    Route::post('/clientes/{client}/contas-anuncios/{adAccount}/boletos', [ClientAdBillingDocumentController::class, 'store'])
        ->name('clients.ad-accounts.billing.store');
    Route::delete('/clientes/{client}/contas-anuncios/{adAccount}/boletos/{document}', [ClientAdBillingDocumentController::class, 'destroy'])
        ->name('clients.ad-accounts.billing.destroy');

    // ── Orçamento de Anúncios (com histórico) ──
    Route::post('/clientes/{client}/orcamento-anuncios', [ClientAdBudgetController::class, 'store'])
        ->name('clients.ad-budgets.store');
    Route::delete('/clientes/{client}/orcamento-anuncios/{adBudget}', [ClientAdBudgetController::class, 'destroy'])
        ->name('clients.ad-budgets.destroy');

    // ── Dossiê de Marca ──
    Route::post('/clientes/{client}/dossies', [BrandDossierController::class, 'store'])
        ->name('clients.dossiers.store');
    Route::get('/clientes/{client}/dossies/{dossier}', [BrandDossierController::class, 'show'])
        ->name('clients.dossiers.show');
    Route::patch('/clientes/{client}/dossies/{dossier}', [BrandDossierController::class, 'update'])
        ->name('clients.dossiers.update');
    Route::post('/clientes/{client}/dossies/{dossier}/avancar', [BrandDossierController::class, 'avancaFase'])
        ->name('clients.dossiers.avancar-fase');
    Route::delete('/clientes/{client}/dossies/{dossier}', [BrandDossierController::class, 'destroy'])
        ->name('clients.dossiers.destroy');

    // ── Concorrentes do dossiê ──
    Route::post('/clientes/{client}/dossies/{dossier}/concorrentes', [DossierCompetitorController::class, 'store'])
        ->name('dossiers.competitors.store');
    Route::patch('/clientes/{client}/dossies/{dossier}/concorrentes/{competitor}', [DossierCompetitorController::class, 'update'])
        ->name('dossiers.competitors.update');
    Route::delete('/clientes/{client}/dossies/{dossier}/concorrentes/{competitor}', [DossierCompetitorController::class, 'destroy'])
        ->name('dossiers.competitors.destroy');

    // ── Personas do dossiê ──
    Route::post('/clientes/{client}/dossies/{dossier}/personas', [DossierPersonaController::class, 'store'])
        ->name('dossiers.personas.store');
    Route::patch('/clientes/{client}/dossies/{dossier}/personas/{persona}', [DossierPersonaController::class, 'update'])
        ->name('dossiers.personas.update');
    Route::delete('/clientes/{client}/dossies/{dossier}/personas/{persona}', [DossierPersonaController::class, 'destroy'])
        ->name('dossiers.personas.destroy');

    // ── Contratos (dashboard geral da agência) ──
    Route::get('/contratos', [ContractController::class, 'index'])
        ->name('contracts.index');

    // ── Campanhas (dashboard interno + insights de IA) ──
    Route::get('/campanhas', [CampaignController::class, 'index'])
        ->name('campaigns.index');
    Route::get('/campanhas/{campaign}', [CampaignController::class, 'show'])
        ->name('campaigns.show');
    Route::patch('/campanhas/{campaign}/descricao', [CampaignController::class, 'updateDescription'])
        ->name('campaigns.update-description');
    Route::patch('/campanhas/{campaign}/status', [CampaignController::class, 'updateManagementStatus'])
        ->name('campaigns.update-status');
    Route::patch('/campanhas/{campaign}/situacao', [CampaignController::class, 'updateManagementSituation'])
        ->name('campaigns.update-situation');
    Route::patch('/campanhas/{campaign}/tier', [CampaignController::class, 'updateOptimizationTier'])
        ->name('campaigns.update-tier');
    Route::patch('/campanhas/{campaign}/metas', [CampaignController::class, 'updateTargets'])
        ->name('campaigns.update-targets');
    Route::patch('/campanhas/{campaign}/trava', [CampaignController::class, 'updateOptimizationLock'])
        ->name('campaigns.update-lock');
    Route::patch('/conjuntos/{adset}/trava', [CampaignController::class, 'updateAdsetOptimizationLock'])
        ->name('adsets.update-lock');
    Route::post('/campanhas/{campaign}/otimizar', [CampaignController::class, 'markOptimized'])
        ->name('campaigns.mark-optimized');

    // ── Orçamentos (visão geral de saldo/pagamento por conta, cross-cliente) ──
    Route::get('/orcamentos', [OrcamentoController::class, 'index'])
        ->name('orcamentos.index');
    Route::patch('/orcamentos/{adAccount}', [OrcamentoController::class, 'updateStatus'])
        ->name('orcamentos.update-status');

    // ── Financeiro ──
    Route::get('/financeiro/categorias', [FinancialCategoryController::class, 'index'])
        ->name('financial-categories.index');
    Route::post('/financeiro/categorias', [FinancialCategoryController::class, 'store'])
        ->name('financial-categories.store');
    Route::patch('/financeiro/categorias/{financialCategory}', [FinancialCategoryController::class, 'update'])
        ->name('financial-categories.update');

    Route::get('/financeiro/lancamentos', [FinancialTransactionController::class, 'index'])
        ->name('financial-transactions.index');
    Route::post('/financeiro/lancamentos', [FinancialTransactionController::class, 'store'])
        ->name('financial-transactions.store');
    Route::patch('/financeiro/lancamentos/{financialTransaction}', [FinancialTransactionController::class, 'update'])
        ->name('financial-transactions.update');
    Route::delete('/financeiro/lancamentos/{financialTransaction}', [FinancialTransactionController::class, 'destroy'])
        ->name('financial-transactions.destroy');

    Route::get('/financeiro/dashboard', [FinancialDashboardController::class, 'index'])
        ->name('financial-dashboard.index');
    Route::post('/campanhas/{campaign}/logs', [CampaignLogController::class, 'store'])
        ->name('campaign-logs.store');
    Route::patch('/campanhas/{campaign}/logs/{log}', [CampaignLogController::class, 'update'])
        ->name('campaign-logs.update');
    Route::delete('/campanhas/{campaign}/logs/{log}', [CampaignLogController::class, 'destroy'])
        ->name('campaign-logs.destroy');
    Route::patch('/insights/{insight}/status', [CampaignInsightController::class, 'updateStatus'])
        ->name('campaign-insights.update-status');

    Route::get('/notificacoes', [\App\Http\Controllers\NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::patch('/notificacoes/{notification}/status', [\App\Http\Controllers\NotificationController::class, 'updateStatus'])
        ->name('notifications.update-status');

    // ── Contratos do cliente ──
    Route::post('/clientes/{client}/contratos', [ContractController::class, 'store'])
        ->name('clients.contracts.store');
    Route::get('/clientes/{client}/contratos/{contract}', [ContractController::class, 'show'])
        ->name('clients.contracts.show');
    Route::patch('/clientes/{client}/contratos/{contract}', [ContractController::class, 'update'])
        ->name('clients.contracts.update');
    Route::patch('/clientes/{client}/contratos/{contract}/status', [ContractController::class, 'updateStatus'])
        ->name('clients.contracts.update-status');
    Route::delete('/clientes/{client}/contratos/{contract}', [ContractController::class, 'destroy'])
        ->name('clients.contracts.destroy');

    // ── Anexos do contrato ──
    Route::post('/contratos/{contract}/anexos', [ContractAttachmentController::class, 'store'])
        ->name('contract-attachments.store');
    Route::delete('/contratos/{contract}/anexos/{attachment}', [ContractAttachmentController::class, 'destroy'])
        ->name('contract-attachments.destroy');

    // ── CRM: Contatos ──
    Route::resource('contatos', ContactController::class)->parameters([
        'contatos' => 'contact',
    ])->names([
        'index'   => 'contacts.index',
        'create'  => 'contacts.create',
        'store'   => 'contacts.store',
        'show'    => 'contacts.show',
        'edit'    => 'contacts.edit',
        'update'  => 'contacts.update',
        'destroy' => 'contacts.destroy',
    ]);

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
    Route::get('/planejamentos/{macroplan}/preview', [MacroPlanController::class, 'preview'])
        ->name('macroplans.preview');
    Route::patch('/planejamentos/{macroplan}', [MacroPlanController::class, 'update'])
        ->name('macroplans.update');
    Route::patch('/planejamentos/{macroplan}/status', [MacroPlanController::class, 'updateStatus'])
        ->name('macroplans.update-status');
    Route::delete('/planejamentos/{macroplan}', [MacroPlanController::class, 'destroy'])
        ->name('macroplans.destroy');

    // ── Import de macroplanejamento via HTML aprovado ──
    Route::get('/planejamentos/importar', [MacroPlanImportController::class, 'create'])
        ->name('macroplans.import.create');
    Route::post('/planejamentos/importar', [MacroPlanImportController::class, 'store'])
        ->name('macroplans.import.store');

    // ── Anexos do macroplanejamento ──
    Route::post('/planejamentos/{macroplan}/anexos', [MacroPlanAttachmentController::class, 'store'])
        ->name('macroplans.attachments.store');
    Route::delete('/planejamentos/{macroplan}/anexos/{attachment}', [MacroPlanAttachmentController::class, 'destroy'])
        ->name('macroplans.attachments.destroy');

    // ── Filas (backlog aguardando sprint) ──
    Route::get('/filas', [FilaController::class, 'index'])
        ->name('fila.index');

    // ── Lista global de tarefas ──
    Route::get('/tarefas', [TaskController::class, 'index'])
        ->name('tasks.index');
    Route::post('/tarefas/lote', [TaskController::class, 'bulkUpdate'])
        ->name('tasks.bulkUpdate');

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

    // ── Chat IA na tarefa ──
    Route::post('/tarefas/{task}/chat', [AiChatController::class, 'storeForTask'])
        ->name('tasks.chat');

    // ── Comentários da tarefa ──
    Route::post('/tarefas/{task}/comentarios', [TaskCommentController::class, 'store'])
        ->name('task-comments.store');
    Route::patch('/tarefas/{task}/comentarios/{comment}', [TaskCommentController::class, 'update'])
        ->name('task-comments.update');
    Route::delete('/tarefas/{task}/comentarios/{comment}', [TaskCommentController::class, 'destroy'])
        ->name('task-comments.destroy');

    // ── Submissão para aprovação (autenticado — designer/gestor) ──
    Route::post('/tarefas/{task}/aprovacao', [TaskApprovalController::class, 'store'])
        ->name('task-approval.store');

    // ── Dashboard central de aprovações ──
    Route::get('/aprovacoes', [\App\Http\Controllers\ApprovalDashboardController::class, 'index'])
        ->name('approvals.index');
    Route::post('/aprovacoes/{round}/enviar', [\App\Http\Controllers\ApprovalDashboardController::class, 'send'])
        ->name('approvals.send');

    // ── Dashboard global de projetos ──
    Route::get('/projetos', [ProjectController::class, 'dashboard'])
        ->name('projects.dashboard');
    Route::patch('/projetos/{project}/quick', [ProjectController::class, 'quickUpdate'])
        ->name('projects.quickUpdate');

    // ── Projeto standalone (sem macroplanejamento vinculado) ──
    Route::get('/projetos/{project}/ver', [ProjectController::class, 'showDirect'])
        ->name('projects.showDirect');
    Route::get('/projetos/{project}/preview', [ProjectController::class, 'preview'])
        ->name('projects.preview');
    Route::patch('/projetos/{project}/planejamento', [ProjectController::class, 'updateMacroplan'])
        ->name('projects.update-macroplan');
    Route::post('/projetos/{project}/tarefas', [TaskController::class, 'storeStandalone'])
        ->name('tasks.storeStandalone');
    Route::patch('/projetos/{project}/tarefas/{task}', [TaskController::class, 'updateStandalone'])
        ->name('tasks.updateStandalone');
    Route::patch('/projetos/{project}/tarefas/{task}/status', [TaskController::class, 'updateStatusStandalone'])
        ->name('tasks.updateStatusStandalone');
    Route::patch('/tarefas/{task}/status', [TaskController::class, 'updateStatusDirect'])
        ->name('tasks.update-status-direct');
    Route::patch('/tarefas/{task}/prioridade', [TaskController::class, 'updatePriority'])
        ->name('tasks.update-priority');
    Route::patch('/tarefas/{task}/situacao', [TaskController::class, 'updateSituation'])
        ->name('tasks.update-situation');
    Route::patch('/tarefas/{task}/responsavel', [TaskController::class, 'updateResponsavel'])
        ->name('tasks.update-responsavel');
    Route::patch('/tarefas/{task}/executor-direto', [TaskController::class, 'updateExecutorDirect'])
        ->name('tasks.update-executor');
    Route::patch('/tarefas/{task}/projeto', [TaskController::class, 'updateProject'])
        ->name('tasks.update-project');
    Route::delete('/projetos/{project}/tarefas/{task}', [TaskController::class, 'destroyStandalone'])
        ->name('tasks.destroyStandalone');

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

    // ── Visões por papel funcional ──
    Route::get('/visoes/{role}', [VisionController::class, 'show'])
        ->name('visoes.show')
        ->where('role', 'direcao_geral|direcao_criativa|coo|gestor_campanhas|head_criativa|head_tech|designer|trafego|dev');

    // ── Painel de Produtividade ──
    Route::get('/produtividade', [\App\Http\Controllers\ProductivityDashboardController::class, 'index'])
        ->name('productivity.index');

    // ── Ecossistema de IA ──
    Route::prefix('ia')->name('ai.')->group(function () {
        // Providers & Chaves
        Route::get('/providers', [\App\Http\Controllers\Ai\AiProviderController::class, 'index'])
            ->name('providers.index');
        Route::post('/providers/{provider}/chaves', [\App\Http\Controllers\Ai\AiProviderController::class, 'storeKey'])
            ->name('providers.keys.store');
        Route::delete('/providers/{provider}/chaves/{key}', [\App\Http\Controllers\Ai\AiProviderController::class, 'destroyKey'])
            ->name('providers.keys.destroy');
        Route::patch('/providers/{provider}/chaves/{key}/toggle', [\App\Http\Controllers\Ai\AiProviderController::class, 'toggleKey'])
            ->name('providers.keys.toggle');

        // Agentes
        Route::get('/agentes', [\App\Http\Controllers\Ai\AiAgentController::class, 'index'])
            ->name('agents.index');
        Route::get('/agentes/novo', [\App\Http\Controllers\Ai\AiAgentController::class, 'create'])
            ->name('agents.create');
        Route::post('/agentes', [\App\Http\Controllers\Ai\AiAgentController::class, 'store'])
            ->name('agents.store');
        Route::get('/agentes/{agent}/editar', [\App\Http\Controllers\Ai\AiAgentController::class, 'edit'])
            ->name('agents.edit');
        Route::patch('/agentes/{agent}', [\App\Http\Controllers\Ai\AiAgentController::class, 'update'])
            ->name('agents.update');
        Route::delete('/agentes/{agent}', [\App\Http\Controllers\Ai\AiAgentController::class, 'destroy'])
            ->name('agents.destroy');
        Route::patch('/agentes/{agent}/toggle', [\App\Http\Controllers\Ai\AiAgentController::class, 'toggleActive'])
            ->name('agents.toggle');
        Route::get('/agentes/{agent}', [\App\Http\Controllers\Ai\AiRunController::class, 'show'])
            ->name('agents.show');

        // Execução (AJAX — chamável de qualquer tela do sistema)
        Route::post('/run', [\App\Http\Controllers\Ai\AiRunController::class, 'run'])
            ->name('run');

        // Uso & Custos
        Route::get('/uso', [\App\Http\Controllers\Ai\AiUsageController::class, 'index'])
            ->name('usage.index');
    });

    // ── Automações ──
    Route::get('/automacoes', [\App\Http\Controllers\AutomationController::class, 'index'])
        ->name('automations.index');
    Route::get('/automacoes/nova', [\App\Http\Controllers\AutomationController::class, 'create'])
        ->name('automations.create');
    Route::post('/automacoes', [\App\Http\Controllers\AutomationController::class, 'store'])
        ->name('automations.store');
    Route::get('/automacoes/{automation}/editar', [\App\Http\Controllers\AutomationController::class, 'edit'])
        ->name('automations.edit');
    Route::patch('/automacoes/{automation}', [\App\Http\Controllers\AutomationController::class, 'update'])
        ->name('automations.update');
    Route::delete('/automacoes/{automation}', [\App\Http\Controllers\AutomationController::class, 'destroy'])
        ->name('automations.destroy');
    Route::patch('/automacoes/{automation}/toggle', [\App\Http\Controllers\AutomationController::class, 'toggleActive'])
        ->name('automations.toggle');
    Route::get('/automacoes/{automation}/logs', [\App\Http\Controllers\AutomationController::class, 'logs'])
        ->name('automations.logs');

    // ── Configurações da Organização (somente admin/owner) ──
    Route::middleware('org.admin')->group(function () {
        Route::get('/configuracoes', [OrganizationSettingsController::class, 'index'])
            ->name('settings.index');
        Route::patch('/configuracoes', [OrganizationSettingsController::class, 'update'])
            ->name('settings.update');

        Route::post('/configuracoes/integracoes', [OrganizationIntegrationController::class, 'store'])
            ->name('settings.integrations.store');
        Route::patch('/configuracoes/integracoes/{integration}', [OrganizationIntegrationController::class, 'update'])
            ->name('settings.integrations.update');
        Route::delete('/configuracoes/integracoes/{integration}', [OrganizationIntegrationController::class, 'destroy'])
            ->name('settings.integrations.destroy');

        Route::get('/configuracoes/integracoes/{integration}/google/conectar', [\App\Http\Controllers\GoogleOAuthController::class, 'connect'])
            ->name('settings.integrations.google.connect');
        Route::get('/configuracoes/integracoes/google/callback', [\App\Http\Controllers\GoogleOAuthController::class, 'callback'])
            ->name('settings.integrations.google.callback');

        Route::post('/configuracoes/api/tokens', [OrganizationSettingsController::class, 'createToken'])
            ->name('settings.tokens.create');
        Route::delete('/configuracoes/api/tokens/{tokenId}', [OrganizationSettingsController::class, 'deleteToken'])
            ->name('settings.tokens.delete');

        Route::post('/configuracoes/equipe', [OrganizationMemberController::class, 'store'])
            ->name('settings.members.store');
        Route::patch('/configuracoes/equipe/{user}', [OrganizationMemberController::class, 'update'])
            ->name('settings.members.update');
        Route::delete('/configuracoes/equipe/{user}', [OrganizationMemberController::class, 'destroy'])
            ->name('settings.members.destroy');

        Route::post('/configuracoes/setores', [\App\Http\Controllers\SectorController::class, 'store'])
            ->name('settings.sectors.store');
        Route::patch('/configuracoes/setores/{sector}', [\App\Http\Controllers\SectorController::class, 'update'])
            ->name('settings.sectors.update');
        Route::delete('/configuracoes/setores/{sector}', [\App\Http\Controllers\SectorController::class, 'destroy'])
            ->name('settings.sectors.destroy');

        Route::post('/configuracoes/mensagens', [\App\Http\Controllers\NotificationTemplateController::class, 'update'])
            ->name('settings.notification-templates.update');
        Route::post('/configuracoes/mensagens/{type}/{channel}/testar', [\App\Http\Controllers\NotificationTemplateController::class, 'test'])
            ->name('settings.notification-templates.test');
    });

});

// ── Cadastro público (sem autenticação) ──
Route::get('/cadastro/{token}', [PublicRegistrationController::class, 'show'])
    ->name('clients.register');

Route::post('/cadastro/{token}', [PublicRegistrationController::class, 'submit'])
    ->name('clients.register.submit');

// ── Aprovação pública (sem autenticação — acesso via link tokenizado) ──
Route::get('/aprovar/{token}', [ApprovalController::class, 'show'])
    ->name('approval.show');

Route::post('/aprovar/{token}', [ApprovalController::class, 'submit'])
    ->name('approval.submit');

// ── Perfil ──
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
});

// ── Portal do Cliente (Nível 3) — login/logout próprios (guard "portal") ──
Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:portal')->group(function () {
        Route::get('/login', [PortalSession::class, 'create'])->name('login');
        Route::post('/login', [PortalSession::class, 'store'])->name('login.store');
    });
    Route::post('/logout', [PortalSession::class, 'destroy'])->middleware('portal')->name('logout');
});

Route::prefix('portal')->name('portal.')->middleware(['portal', 'portal.client'])->group(function () {
    Route::get('/', [PortalDashboard::class, 'index'])->name('dashboard');
    Route::post('/trocar-cliente/{client}', [PortalClientContext::class, 'switch'])->name('client-context.switch');
    Route::get('/projetos', [PortalProjects::class, 'index'])->name('projects.index');
    Route::get('/projetos/{macroplan}', [PortalProjects::class, 'show'])->name('projects.show');
    Route::get('/tarefas/{task}', [PortalProjects::class, 'showTask'])->name('tasks.show');
    Route::post('/tarefas/{task}/comentarios', [PortalTaskComments::class, 'store'])->name('tasks.comments.store');
    Route::get('/chamados', [PortalTickets::class, 'index'])->name('tickets.index');
    Route::get('/chamados/novo', [PortalTickets::class, 'create'])->name('tickets.create');
    Route::post('/chamados', [PortalTickets::class, 'store'])->name('tickets.store');
    Route::get('/chamados/{task}', [PortalTickets::class, 'show'])->name('tickets.show');
    Route::get('/reunioes', [PortalMeetings::class, 'index'])->name('meetings.index');
    Route::get('/reunioes/{meeting}', [PortalMeetings::class, 'show'])->name('meetings.show');
    Route::get('/aprovacoes', [PortalApprovals::class, 'index'])->name('approvals.index');
    Route::get('/aprovacoes/{round}', [PortalApprovals::class, 'show'])->name('approvals.show');
    Route::post('/aprovacoes/{round}/decidir', [PortalApprovals::class, 'decide'])->name('approvals.decide');
    Route::get('/campanhas', [PortalCampaigns::class, 'index'])->name('campaigns.index');
    Route::get('/boletos', [PortalBilling::class, 'index'])->name('boletos.index');
    Route::get('/atendimento', [PortalServiceDiagnostics::class, 'index'])->name('service-diagnostics.index');
    Route::get('/atendimento/{integration}', [PortalServiceDiagnostics::class, 'integration'])->name('service-diagnostics.integration');
    Route::get('/atendimento/{integration}/diagnosticos/{diagnostic}', [PortalServiceDiagnostics::class, 'show'])->name('service-diagnostics.show');
    Route::get('/conta', [PortalAccount::class, 'index'])->name('account');
});

// ── Criar acesso ao portal (agência) ──
Route::middleware(['auth', 'not-client'])->group(function () {
    Route::post('/clientes/{client}/acesso-portal', [ClientPortalAccessController::class, 'store'])
        ->name('clients.portal-access.store');
    Route::delete('/clientes/{client}/acesso-portal/{portalContact}', [ClientPortalAccessController::class, 'destroy'])
        ->name('clients.portal-access.destroy');
});

// ── SuperAdmin ──
Route::prefix('superadmin')->name('superadmin.')->middleware(['auth', 'superadmin'])->group(function () {
    Route::get('/', [SuperAdminDashboard::class, 'index'])->name('dashboard');
    Route::resource('organizations', SuperAdminOrgs::class)->except(['show', 'destroy']);
    Route::get('/reset-operacional', [App\Http\Controllers\SuperAdmin\ResetOperacionalController::class, 'index'])->name('reset-operacional');
    Route::post('/reset-operacional', [App\Http\Controllers\SuperAdmin\ResetOperacionalController::class, 'execute'])->name('reset-operacional.execute');
});

require __DIR__.'/auth.php';

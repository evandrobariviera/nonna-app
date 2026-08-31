<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientOnboarding;
use App\Models\Contract;
use App\Models\FunctionalRole;
use App\Models\Opportunity;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Esteira de onboarding do cliente novo — dois momentos:
 *
 *  1. start()  — oportunidade "novo cliente" fechada como ganha
 *     (OpportunityController::win). Cria o registro de onboarding e dispara o
 *     evento `opportunity_won` pro n8n (WhatsApp de boas-vindas + link do
 *     cadastro pro contato).
 *
 *  2. onRegistrationComplete() — cliente preencheu o cadastro público
 *     (ClientObserver, quando registration_completed_at é setado). Cria o
 *     Contract (rascunho), abre o Ticket "Contrato em análise" pro Financeiro,
 *     avança o checklist e dispara `client_registration_completed` pro n8n
 *     (grupo WhatsApp + pasta Drive + contrato no Docs + confirmação pro cliente).
 *
 * As duas etapas são idempotentes.
 */
class ClientOnboardingService
{
    public function __construct(private N8nEventService $n8n) {}

    // Cria só o registro de onboarding (Fase 1, checklist zerado) — sem token,
    // sem webhook. Usado quando o time inicia o acompanhamento à mão pra um
    // cliente cadastrado por fora do funil.
    public function createRecord(Client $client, ?string $responsibleId = null): ClientOnboarding
    {
        return $client->onboarding ?? ClientOnboarding::create([
            'client_id'       => $client->id,
            'current_phase'   => 'fase1',
            'responsible_id'  => $responsibleId,
            'fase1_checklist' => ClientOnboarding::$defaultChecklists['fase1'],
            'fase2_checklist' => ClientOnboarding::$defaultChecklists['fase2'],
            'fase3_checklist' => ClientOnboarding::$defaultChecklists['fase3'],
            'fase4_checklist' => ClientOnboarding::$defaultChecklists['fase4'],
            'fase5_checklist' => ClientOnboarding::$defaultChecklists['fase5'],
        ]);
    }

    // Fluxo completo do "ganho" (novo cliente): cria o onboarding, gera o token
    // de cadastro e dispara o WhatsApp de boas-vindas + link (n8n).
    public function start(Client $client, ?Opportunity $opportunity, ?string $responsibleId = null): ClientOnboarding
    {
        $onboarding = $this->createRecord(
            $client,
            $responsibleId ?? $opportunity?->assigned_to ?? $opportunity?->created_by
        );

        if (!$client->registration_token) {
            $client->generateRegistrationToken();
        }

        $registrationUrl = route('clients.register', $client->registration_token);

        $sent = $this->n8n->dispatch('opportunity_won', $client->organization, [
            'opportunity' => $opportunity ? [
                'id'                 => $opportunity->id,
                'title'              => $opportunity->title,
                'type'               => $opportunity->type,
                'proposed_fee'       => $opportunity->proposed_fee,
                'proposed_ad_budget' => $opportunity->proposed_ad_budget,
                'contract_months'    => $opportunity->contract_months,
                'services'           => $this->serviceLabels($opportunity->services_interest ?? []),
                'notes'              => $opportunity->notes,
            ] : null,
            'client' => [
                'id'               => $client->id,
                'company_name'     => $client->company_name,
                'registration_url' => $registrationUrl,
                'app_url'          => route('clients.show', $client),
            ],
            'contact' => $this->contactPayload($opportunity, $client),
        ]);

        // Otimista: o App pediu pro n8n mandar; marca como enviado. Se o n8n
        // falhar, o item pode ser desmarcado à mão na aba Onboarding.
        if ($sent) {
            $onboarding->markDone('whatsapp_boas_vindas', 'link_cadastro_enviado');
        }

        return $onboarding->fresh();
    }

    public function onRegistrationComplete(Client $client): void
    {
        $opportunity = $this->wonOpportunity($client);
        $onboarding = $client->onboarding
            ?? $this->createRecord($client, $opportunity?->assigned_to ?? $opportunity?->created_by);

        // Idempotência — cadastro pode ser re-submetido; não recria contrato/ticket.
        if ($onboarding->fase1_checklist['cadastro_preenchido'] ?? false) {
            return;
        }

        // Autor dos registros — sem usuário logado (rota pública). Prioriza quem
        // criou/tocou a oportunidade, depois o responsável do onboarding, e por
        // fim a 1ª pessoa do Financeiro (o Ticket exige created_by NOT NULL).
        $authorId = $opportunity?->created_by
            ?? $opportunity?->assigned_to
            ?? $onboarding->responsible_id
            ?? $this->financeUsers($client)->first()?->id
            ?? \App\Models\User::query()->value('id');

        [$contract, $ticket] = DB::connection('pgsql')->transaction(function () use ($client, $opportunity, $authorId) {
            $contract = $this->createDraftContract($client, $opportunity, $authorId);
            $ticket   = $this->createReviewTicket($client, $contract, $authorId);
            return [$contract, $ticket];
        });

        $onboarding->markDone('cadastro_preenchido', 'ticket_contrato_aberto');
        $onboarding->update(['current_phase' => 'fase2']);

        $this->notifyFinance($client, $ticket);

        $this->n8n->dispatch('client_registration_completed', $client->organization, [
            'client' => [
                'id'                         => $client->id,
                'company_name'               => $client->company_name,
                'tax_id'                     => $client->tax_id,
                'segment'                    => $client->segment,
                'address'                    => $client->address,
                'zip_code'                   => $client->zip_code,
                'contact_email'              => $client->contact_email,
                'contact_phone'              => $client->contact_phone,
                'responsible_name'           => $client->responsible_name,
                'responsible_cpf'            => $client->responsible_cpf,
                'responsible_rg'             => $client->responsible_rg,
                'responsible_birthdate'      => optional($client->responsible_birthdate)->format('Y-m-d'),
                'responsible_address'        => $client->responsible_address,
                'responsible_marital_status' => $client->responsible_marital_status,
                'payment_method'             => $client->payment_method,
                'billing_day'                => $client->billing_day,
                'billing_email'              => $client->billing_email,
                'billing_whatsapp'           => $client->billing_whatsapp,
                'billing_notes'              => $client->billing_notes,
                'app_url'                    => route('clients.show', $client),
            ],
            'contact' => $this->contactPayload($opportunity, $client),
            'contract' => [
                'id'               => $contract->id,
                'title'            => $contract->title,
                'fee_value'        => $contract->fee_value,
                'fee_type'         => $contract->fee_type,
                'start_date'       => optional($contract->start_date)->format('Y-m-d'),
                'end_date'         => optional($contract->end_date)->format('Y-m-d'),
                'months'           => $opportunity?->contract_months,
                'payment_method'   => $contract->payment_method,
                'billing_day'      => $contract->billing_day,
                'app_url'          => route('clients.contracts.show', [$client, $contract]),
                'doc_callback_url' => url("/api/onboarding/{$client->id}/step"),
            ],
            'services'            => $this->serviceLabels($client->contracted_services ?: ($opportunity->services_interest ?? [])),
            'negotiation_summary' => $opportunity?->notes,
            'ticket' => [
                'id'      => $ticket->id,
                'title'   => $ticket->title,
                'app_url' => route('tasks.show', $ticket),
            ],
        ]);
    }

    /**
     * Confirmação vinda do n8n de que um passo externo rodou (ver
     * Api\OnboardingCallbackController). step = chave do checklist.
     */
    public function confirmStep(Client $client, string $step, ?string $contractUrl = null): bool
    {
        $onboarding = $client->onboarding;
        if (!$onboarding) {
            return false;
        }

        $ok = $onboarding->setChecklistItem($step, true);

        if ($contractUrl) {
            $contract = $client->contracts()->where('status', 'rascunho')->latest('created_at')->first();
            $contract?->update(['document_url' => $contractUrl]);
        }

        return $ok;
    }

    private function createDraftContract(Client $client, ?Opportunity $opportunity, ?string $authorId): Contract
    {
        $months = $opportunity?->contract_months;
        $start  = now();

        return Contract::create([
            'organization_id' => $client->organization_id,
            'client_id'       => $client->id,
            'title'           => 'Contrato — ' . $client->company_name,
            'status'          => 'rascunho',
            'start_date'      => $start,
            'end_date'        => $months ? $start->copy()->addMonths($months) : null,
            'fee_value'       => $opportunity?->proposed_fee,
            'fee_type'        => 'mensal',
            'payment_method'  => $client->payment_method,
            'billing_day'     => $client->billing_day,
            'renewal_type'    => 'automatica',
            'notes'           => $opportunity?->notes,
            'created_by'      => $authorId,
        ]);
    }

    private function createReviewTicket(Client $client, Contract $contract, ?string $authorId): Task
    {
        return Task::create([
            'organization_id' => $client->organization_id,
            'client_id'       => $client->id,
            'title'           => 'Contrato em análise — ' . $client->displayName(),
            'description'     => "Cadastro do cliente concluído e contrato gerado. Conferir os dados e o documento antes de enviar pra assinatura.\n\nContrato: " . route('clients.contracts.show', [$client, $contract]),
            'task_type'       => 'administrativo',
            'status'          => 'backlog',
            'is_ticket'       => true,
            'origin'          => 'onboarding',
            'created_by'      => $authorId,
        ]);
    }

    private function financeUsers(Client $client)
    {
        return FunctionalRole::where('key', 'administrativo_e_financeiro')->first()?->users ?? collect();
    }

    private function notifyFinance(Client $client, Task $ticket): void
    {
        $users = $this->financeUsers($client);
        if ($users->isEmpty()) {
            return;
        }

        app(SystemNotificationService::class)->send(
            'onboarding.contrato_analise',
            $users,
            ['client_name' => $client->displayName()],
            route('tasks.show', $ticket),
            $ticket,
            $client->organization_id,
        );
    }

    private function wonOpportunity(Client $client): ?Opportunity
    {
        return $client->opportunities()
            ->where('stage', 'ganho')
            ->orderByDesc('won_at')
            ->first();
    }

    private function contactPayload(?Opportunity $opportunity, Client $client): array
    {
        $contact = $opportunity?->contact ?? $client->contacts()->orderByDesc('client_contacts.is_primary')->first();

        return [
            'id'       => $contact?->id,
            'name'     => $contact?->name ?? $client->responsible_name,
            'whatsapp' => $contact?->whatsapp ?? $client->contact_phone,
            'email'    => $contact?->email ?? $client->contact_email,
        ];
    }

    private function serviceLabels(array $keys): array
    {
        return collect($keys)->map(fn ($k) => Client::$services[$k] ?? $k)->values()->all();
    }
}

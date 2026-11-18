<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multiorganização (2026-11-18): tabelas ligadas a cliente que ainda não tinham
 * organization_id ganham a coluna, preenchida pelo cliente dono do registro. Com
 * ela, os models entram no filtro automático por organização (Tenantable) — antes,
 * qualquer consulta sem cliente somava os registros de todas as organizações.
 *
 * Coluna nullable de propósito: um registro sem organização nunca derruba gravação;
 * o Tenantable preenche pelo cliente quando não há organização atual (jobs, Portal).
 */
return new class extends Migration
{
    /** tabela => como achar a organização (SQL que devolve organization_id por linha "t") */
    private function tables(): array
    {
        $viaClient = 'select c.organization_id from clients c where c.id = t.client_id';

        return [
            'ai_token_usage'              => $viaClient,
            'brand_dossiers'              => $viaClient,
            'client_ad_accounts'          => $viaClient,
            'client_ad_budgets'           => $viaClient,
            'client_contacts'             => $viaClient,
            'client_credential_requests'  => $viaClient,
            'client_credentials'          => $viaClient,
            'client_integrations'         => $viaClient,
            'client_lead_sources'         => $viaClient,
            'client_leads'                => $viaClient,
            'client_links'                => $viaClient,
            'client_modules'              => $viaClient,
            'client_onboardings'          => $viaClient,
            'portal_password_setup_tokens' => $viaClient,
            'service_conversations'       => $viaClient,
            'service_diagnostics'         => $viaClient,
            'client_lead_opportunities'   => 'select l.organization_id from client_leads l where l.id = t.client_lead_id',
            'client_ad_billing_documents' => 'select a.organization_id from client_ad_accounts a where a.id = t.client_ad_account_id',
        ];
    }

    public function up(): void
    {
        $db = DB::connection('pgsql');
        // Até hoje só existia uma organização: o que não tem cliente (ex.: uso de IA
        // interno) pertence a ela.
        $onlyOrg = $db->table('organizations')->count() === 1 ? $db->table('organizations')->value('id') : null;

        foreach ($this->tables() as $table => $source) {
            if (!Schema::connection('pgsql')->hasColumn($table, 'organization_id')) {
                Schema::connection('pgsql')->table($table, function (Blueprint $t) {
                    $t->uuid('organization_id')->nullable()->index();
                });
            }

            // Ordem do array garante que client_leads/client_ad_accounts já estão
            // preenchidas quando oportunidades e documentos leem delas.
            $db->statement("update {$table} t set organization_id = ({$source}) where t.organization_id is null");

            if ($onlyOrg) {
                $db->table($table)->whereNull('organization_id')->update(['organization_id' => $onlyOrg]);
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables()) as $table) {
            Schema::connection('pgsql')->table($table, fn (Blueprint $t) => $t->dropColumn('organization_id'));
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Prestação de contas dos anúncios (Central de Campanhas).
 *
 * - client_ad_billing_documents ganha `reference_month`: o mês a que a nota
 *   (tipo "nota") ou o boleto/PIX se refere. A nota de outubro é emitida e
 *   enviada em novembro, por isso não dá para usar a data de envio.
 * - ad_account_statements: o que não dá para calcular no extrato mensal de
 *   cada conta — saldo inicial (o do primeiro mês, ou uma correção manual) e
 *   a observação do mês ("precisamos trocar o CNPJ").
 * - ad_statement_deliveries: cada envio de notas ao cliente (um aviso por
 *   envio, não por arquivo) e quando ele abriu.
 * - Mensagem padrão nova "prestacao_contas", já assinada por quem recebe o
 *   "financeiro" hoje (mesmos canais), para ninguém ficar sem receber.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('client_ad_billing_documents', function (Blueprint $table) {
            $table->date('reference_month')->nullable()->after('due_date');
            $table->index(['client_ad_account_id', 'reference_month']);
        });

        Schema::connection('pgsql')->create('ad_account_statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('client_ad_account_id');
            $table->date('month');
            $table->decimal('opening_balance', 12, 2)->nullable();
            $table->string('opening_balance_source', 10)->nullable(); // auto | manual
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('client_ad_account_id')->references('id')->on('client_ad_accounts')->cascadeOnDelete();
            $table->unique(['client_ad_account_id', 'month']);
        });

        Schema::connection('pgsql')->create('ad_statement_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('client_id');
            $table->date('month');
            $table->jsonb('document_ids')->default('[]');
            $table->decimal('total', 12, 2)->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->index(['client_id', 'month']);
        });

        $now = now();
        $templates = [
            'whatsapp' => [null, "Oi {{contato}}! As notas fiscais dos anúncios de {{cliente}} referentes a {{mes}} já estão disponíveis ({{quantidade}}, total de {{valor}}).\n\nPara baixar: {{link_prestacao}}"],
            'email'    => ['Notas fiscais dos anúncios — {{mes}} — {{cliente}}', "Olá {{contato}},\n\nAs notas fiscais dos investimentos em anúncios de {{cliente}} referentes a {{mes}} já estão disponíveis.\n\nQuantidade: {{quantidade}}\nTotal: {{valor}}\n\nPara baixar as notas e ver o extrato do mês: {{link_prestacao}}\n\nQualquer dúvida, estamos à disposição.\n\nEquipe Nonna"],
        ];
        $rows = [];
        foreach (DB::connection('pgsql')->table('organizations')->pluck('id') as $orgId) {
            foreach ($templates as $channel => [$subject, $body]) {
                $rows[] = ['id' => (string) Str::uuid(), 'organization_id' => $orgId, 'type' => 'prestacao_contas', 'channel' => $channel,
                    'subject' => $subject, 'body' => $body, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        if ($rows) {
            DB::connection('pgsql')->table('notification_templates')->insertOrIgnore($rows);
        }

        // Quem recebe o "financeiro" (boletos) passa a receber as notas também.
        $subs = DB::connection('pgsql')->table('client_contact_subscriptions')->where('type', 'financeiro')->get(['client_contact_id', 'channels']);
        $copy = $subs->map(fn ($s) => ['id' => (string) Str::uuid(), 'client_contact_id' => $s->client_contact_id, 'type' => 'prestacao_contas',
            'channels' => $s->channels, 'created_at' => $now, 'updated_at' => $now])->all();
        foreach (array_chunk($copy, 200) as $chunk) {
            DB::connection('pgsql')->table('client_contact_subscriptions')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('client_contact_subscriptions')->where('type', 'prestacao_contas')->delete();
        DB::connection('pgsql')->table('notification_templates')->where('type', 'prestacao_contas')->delete();
        Schema::connection('pgsql')->dropIfExists('ad_statement_deliveries');
        Schema::connection('pgsql')->dropIfExists('ad_account_statements');
        Schema::connection('pgsql')->table('client_ad_billing_documents', function (Blueprint $table) {
            $table->dropIndex(['client_ad_account_id', 'reference_month']);
            $table->dropColumn('reference_month');
        });
    }
};

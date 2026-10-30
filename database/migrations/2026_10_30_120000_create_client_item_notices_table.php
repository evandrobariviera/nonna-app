<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Aviso ao cliente de que um planejamento/campanha/projeto entrou em execução:
    // um por item, para sempre (unique). O token é o link sem senha do documento.
    public function up(): void
    {
        Schema::connection('pgsql')->create('client_item_notices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->uuid('item_id');
            $table->string('token', 40)->unique();
            $table->timestamp('due_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['item_type', 'item_id']);
            $table->index(['sent_at', 'due_at']);
        });

        $this->seedSubscriptions();
        $this->seedTemplates();
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('client_item_notices');
    }

    // Decisão do Evandro (30/10): começa por quem já recebe Aprovação, nos mesmos canais.
    private function seedSubscriptions(): void
    {
        $db = DB::connection('pgsql');

        foreach (['planejamento_ativo', 'item_ativo'] as $type) {
            $rows = $db->table('client_contact_subscriptions')->where('type', 'aprovacao')->get(['client_contact_id', 'channels']);

            foreach ($rows as $row) {
                $exists = $db->table('client_contact_subscriptions')
                    ->where('client_contact_id', $row->client_contact_id)->where('type', $type)->exists();

                if (!$exists) {
                    $db->table('client_contact_subscriptions')->insert([
                        'id'                => (string) Str::uuid7(),
                        'client_contact_id' => $row->client_contact_id,
                        'type'              => $type,
                        'channels'          => $row->channels,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                }
            }
        }
    }

    private function seedTemplates(): void
    {
        $db = DB::connection('pgsql');

        $planWhatsapp = <<<'TXT'
Oi, {{contato}}! Tudo bem?

O {{planejamento}} entrou em execução.

Duração: {{periodo}}

{{lista_itens}}

Veja o documento completo: {{link_documento}}

Tudo isso também fica organizado no Portal do Cliente: {{link_portal}}
TXT;

        $planEmail = <<<'TXT'
Olá {{contato}}, tudo bem?

O {{planejamento}} entrou em execução.

Duração: {{periodo}}

{{lista_itens}}

Veja o documento completo:
{{link_documento}}

Tudo isso também fica organizado no Portal do Cliente:
{{link_portal}}

Equipe Nonna
TXT;

        $itemWhatsapp = <<<'TXT'
Oi, {{contato}}! Tudo bem?

Temos novidade no trabalho de vocês. Entrou em execução:

{{lista_itens}}

Tudo isso também fica organizado no Portal do Cliente: {{link_portal}}
TXT;

        $itemEmail = <<<'TXT'
Olá {{contato}}, tudo bem?

Temos novidade no trabalho de vocês. Entrou em execução:

{{lista_itens}}

Tudo isso também fica organizado no Portal do Cliente:
{{link_portal}}

Equipe Nonna
TXT;

        $templates = [
            ['planejamento_ativo', 'whatsapp', null, $planWhatsapp],
            ['planejamento_ativo', 'email', '{{planejamento}} em execução', $planEmail],
            ['item_ativo', 'whatsapp', null, $itemWhatsapp],
            ['item_ativo', 'email', 'Novidade no trabalho de vocês: {{cliente}}', $itemEmail],
        ];

        foreach ($db->table('organizations')->pluck('id') as $organizationId) {
            foreach ($templates as [$type, $channel, $subject, $body]) {
                $exists = $db->table('notification_templates')
                    ->where('organization_id', $organizationId)->where('type', $type)->where('channel', $channel)->exists();

                if (!$exists) {
                    $db->table('notification_templates')->insert([
                        'id'              => (string) Str::uuid7(),
                        'organization_id' => $organizationId,
                        'type'            => $type,
                        'channel'         => $channel,
                        'subject'         => $subject,
                        'body'            => str_replace("\r\n", "\n", $body),
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }
        }
    }
};

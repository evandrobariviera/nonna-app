<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mensagem Padrão "Cadastro Inicial — Reenvio do Link" (cadastro_reenvio), usada pelo
 * botão "Reenviar ao cliente" do card Link de Cadastro. Já nasce com texto pra cada
 * organização — sem texto o disparo é ignorado. Editável em Configurações > Mensagens Padrão.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $templates = [
            'whatsapp' => [
                null,
                "Oi {{contato}}! Tudo bem? Segue de novo o link do cadastro da {{cliente}} com a Nonna. São só alguns dados da empresa e do responsável, pra gente seguir com o contrato e os primeiros passos:\n\n{{link_cadastro}}\n\nQualquer dúvida, é só chamar por aqui!",
            ],
            'email' => [
                'Cadastro da {{cliente}} — Nonna',
                "Olá {{contato}}, tudo bem?\n\nSegue de novo o link do cadastro da {{cliente}} com a Nonna. São só alguns dados da empresa e do responsável, pra seguirmos com o contrato e os primeiros passos:\n\n{{link_cadastro}}\n\nQualquer dúvida, estamos à disposição.\n\nEquipe Nonna",
            ],
        ];

        foreach (DB::connection('pgsql')->table('organizations')->pluck('id') as $orgId) {
            foreach ($templates as $channel => [$subject, $body]) {
                $exists = DB::connection('pgsql')->table('notification_templates')
                    ->where('organization_id', $orgId)->where('type', 'cadastro_reenvio')->where('channel', $channel)
                    ->exists();
                if ($exists) {
                    continue;
                }

                DB::connection('pgsql')->table('notification_templates')->insert([
                    'id'              => (string) Str::uuid(),
                    'organization_id' => $orgId,
                    'type'            => 'cadastro_reenvio',
                    'channel'         => $channel,
                    'subject'         => $subject,
                    'body'            => $body,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('notification_templates')->where('type', 'cadastro_reenvio')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Acesso ao Portal deixa de ser global no Contato e passa a ser por vínculo
// (client_contacts) — um contato que atende vários clientes pode ter o
// Portal liberado num e revogado noutro. Login continua por e-mail/senha
// no Contato (uma senha só); o que muda é "pra qual cliente ele enxerga
// dados", controlado por essa flag no vínculo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('client_contacts', function (Blueprint $table) {
            $table->boolean('portal_access_enabled')->default(false)->after('is_primary');
        });

        // Backfill: contato que já tinha o portal liberado (coluna antiga, global)
        // mantém o acesso em TODOS os clientes que já estava vinculado — ninguém
        // perde acesso no deploy.
        DB::connection('pgsql')->statement(<<<'SQL'
            UPDATE client_contacts
            SET portal_access_enabled = true
            WHERE contact_id IN (SELECT id FROM contacts WHERE portal_access_enabled = true)
        SQL);

        // O índice único parcial antigo garantia "só um contato com portal ativo
        // por e-mail" usando a flag global. Ela deixa de existir — o proxy correto
        // agora é "tem senha definida" (== é um identificador de login em uso).
        DB::connection('pgsql')->statement('DROP INDEX IF EXISTS contacts_org_email_portal_unique');
        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE UNIQUE INDEX contacts_org_email_login_unique
            ON contacts (organization_id, lower(email))
            WHERE password IS NOT NULL
        SQL);

        Schema::connection('pgsql')->table('contacts', function (Blueprint $table) {
            $table->dropColumn('portal_access_enabled');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('contacts', function (Blueprint $table) {
            $table->boolean('portal_access_enabled')->default(false)->after('remember_token');
        });

        DB::connection('pgsql')->statement(<<<'SQL'
            UPDATE contacts
            SET portal_access_enabled = true
            WHERE id IN (SELECT contact_id FROM client_contacts WHERE portal_access_enabled = true)
        SQL);

        DB::connection('pgsql')->statement('DROP INDEX IF EXISTS contacts_org_email_login_unique');
        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE UNIQUE INDEX contacts_org_email_portal_unique
            ON contacts (organization_id, lower(email))
            WHERE portal_access_enabled = true
        SQL);

        Schema::connection('pgsql')->table('client_contacts', function (Blueprint $table) {
            $table->dropColumn('portal_access_enabled');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('contacts', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->rememberToken()->after('password');
            $table->boolean('portal_access_enabled')->default(false)->after('remember_token');
            $table->timestamp('portal_last_login_at')->nullable()->after('portal_access_enabled');
        });

        // Índice único parcial: só passa a exigir e-mail único quando o
        // acesso ao portal está ativo — cadastros de CRM com e-mail
        // duplicado (já existentes) continuam válidos sem precisar de
        // limpeza retroativa.
        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE UNIQUE INDEX contacts_org_email_portal_unique
            ON contacts (organization_id, lower(email))
            WHERE portal_access_enabled = true
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP INDEX IF EXISTS contacts_org_email_portal_unique');

        Schema::connection('pgsql')->table('contacts', function (Blueprint $table) {
            $table->dropColumn(['password', 'remember_token', 'portal_access_enabled', 'portal_last_login_at']);
        });
    }
};

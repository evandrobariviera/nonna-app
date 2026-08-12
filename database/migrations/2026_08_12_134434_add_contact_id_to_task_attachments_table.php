<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Mesmo padrão de task_comments (2026_07_21_000004): uploaded_by (User) vira
    // opcional, ganha uploaded_by_contact_id (Contact) — anexo enviado pelo Portal
    // não tem User nenhum por trás. XOR check garante que sempre tem exatamente um
    // autor, nunca os dois nem nenhum.
    public function up(): void
    {
        Schema::connection('pgsql')->table('task_attachments', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable()->change();
            $table->foreignUuid('uploaded_by_contact_id')->nullable()->after('uploaded_by')
                ->constrained('contacts')->nullOnDelete();
        });

        DB::connection('pgsql')->statement(<<<'SQL'
            ALTER TABLE task_attachments ADD CONSTRAINT task_attachments_uploader_xor
            CHECK (
                (uploaded_by IS NOT NULL AND uploaded_by_contact_id IS NULL)
                OR (uploaded_by IS NULL AND uploaded_by_contact_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('ALTER TABLE task_attachments DROP CONSTRAINT IF EXISTS task_attachments_uploader_xor');

        Schema::connection('pgsql')->table('task_attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by_contact_id');
            $table->foreignId('uploaded_by')->nullable(false)->change();
        });
    }
};

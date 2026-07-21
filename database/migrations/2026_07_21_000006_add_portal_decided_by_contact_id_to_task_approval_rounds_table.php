<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('task_approval_rounds', function (Blueprint $table) {
            // portal_decided_by (users) fica congelado como legado — decisões
            // via portal agora são sempre de um Contact, nunca mais de um User.
            $table->foreignUuid('portal_decided_by_contact_id')->nullable()->after('portal_decided_by')
                ->constrained('contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_approval_rounds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('portal_decided_by_contact_id');
        });
    }
};

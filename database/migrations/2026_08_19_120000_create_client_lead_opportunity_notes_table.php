<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Thread de notas do Kanban de Leads — escrita tanto pelo lado interno (User)
// quanto pelo Portal (Contact), mesmo par XOR que task_comments já usa, pra
// dar transparência de quem mexeu dos dois lados. Também guarda as mudanças
// de estágio como entradas de sistema (from_stage/to_stage preenchidos),
// evitando uma tabela de histórico separada só pra isso.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('client_lead_opportunity_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_lead_opportunity_id')->constrained('client_lead_opportunities')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->text('body');
            $table->string('from_stage', 30)->nullable();
            $table->string('to_stage', 30)->nullable();
            $table->timestamps();

            $table->index(['client_lead_opportunity_id', 'created_at']);
        });

        DB::connection('pgsql')->statement(<<<'SQL'
            ALTER TABLE client_lead_opportunity_notes ADD CONSTRAINT client_lead_opportunity_notes_author_xor
            CHECK (
                (user_id IS NOT NULL AND contact_id IS NULL)
                OR (user_id IS NULL AND contact_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('client_lead_opportunity_notes');
    }
};

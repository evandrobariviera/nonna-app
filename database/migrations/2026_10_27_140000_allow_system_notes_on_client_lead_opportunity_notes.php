<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Notas automáticas ("Entrou de novo", "Juntou o cartão repetido") não têm
// autor: a constraint passa de "exatamente um" para "no máximo um" autor.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement('ALTER TABLE client_lead_opportunity_notes DROP CONSTRAINT IF EXISTS client_lead_opportunity_notes_author_xor');
        DB::connection('pgsql')->statement(<<<'SQL'
            ALTER TABLE client_lead_opportunity_notes ADD CONSTRAINT client_lead_opportunity_notes_author_xor
            CHECK (user_id IS NULL OR contact_id IS NULL)
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('ALTER TABLE client_lead_opportunity_notes DROP CONSTRAINT IF EXISTS client_lead_opportunity_notes_author_xor');
        DB::connection('pgsql')->statement(<<<'SQL'
            ALTER TABLE client_lead_opportunity_notes ADD CONSTRAINT client_lead_opportunity_notes_author_xor
            CHECK (
                (user_id IS NOT NULL AND contact_id IS NULL)
                OR (user_id IS NULL AND contact_id IS NOT NULL)
            )
        SQL);
    }
};

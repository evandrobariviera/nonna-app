<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Pivot simples (sem role/is_primary como client_contacts) — só marca quais
    // contatos do cliente participam da reunião, pra poder notificá-los.
    public function up(): void
    {
        Schema::connection('pgsql')->create('meeting_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['meeting_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('meeting_contacts');
    }
};

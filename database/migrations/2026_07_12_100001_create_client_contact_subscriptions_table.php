<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('client_contact_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_contact_id')->constrained('client_contacts')->cascadeOnDelete();
            // aprovacao | financeiro | feedback_campanhas | cobranca
            $table->string('type', 40);
            // ["whatsapp","email"]
            $table->jsonb('channels');
            $table->timestamps();

            $table->unique(['client_contact_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('client_contact_subscriptions');
    }
};

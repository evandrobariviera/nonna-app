<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('service_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('client_integration_id')->constrained('client_integrations')->cascadeOnDelete();

            $table->string('external_thread_id'); // id da conversa/chat na origem (uazapi ou CRM)
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 30)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);

            $table->timestamps();

            $table->unique(['client_integration_id', 'external_thread_id']);
            $table->index(['client_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('service_conversations');
    }
};

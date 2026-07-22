<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('service_messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('conversation_id')->constrained('service_conversations')->cascadeOnDelete();

            $table->string('direction', 10); // in | out
            $table->string('sender_name')->nullable();
            $table->text('body')->nullable();
            $table->string('media_url', 500)->nullable();

            $table->timestamp('sent_at');
            $table->jsonb('raw_payload')->nullable(); // payload cru da origem, sem perder nada

            $table->timestamps();

            $table->index(['conversation_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('service_messages');
    }
};

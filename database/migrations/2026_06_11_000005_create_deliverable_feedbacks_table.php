<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('deliverable_feedbacks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('token_id')->constrained('task_approval_tokens')->cascadeOnDelete();
            $table->foreignUuid('attachment_id')->constrained('task_attachments')->cascadeOnDelete();
            // approved | changes_requested
            $table->string('status', 30);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['token_id', 'attachment_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('deliverable_feedbacks');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Mesmo padrão de macro_plan_attachments — Projeto (com ou sem macroplano)
    // não tinha jeito de anexar arquivo próprio, só os clickup_attachments (jsonb,
    // só leitura, vindos do import do ClickUp).
    public function up(): void
    {
        Schema::connection('pgsql')->create('project_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('filename', 300);
            $table->string('disk_path', 500);
            $table->string('disk', 20)->default('r2');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('project_attachments');
    }
};

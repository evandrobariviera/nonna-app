<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // "Item de ação" — comentário virado checklist atribuído a alguém (igual ClickUp:
    // ícone na ação do comentário, escolhe a pessoa, cria o item). source_comment_id é
    // só a origem/rastro — o item sobrevive à remoção do comentário (nullOnDelete).
    public function up(): void
    {
        Schema::connection('pgsql')->create('task_checklist_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('source_comment_id')->nullable()->constrained('task_comments')->nullOnDelete();
            $table->text('title');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['task_id', 'done']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('task_checklist_items');
    }
};

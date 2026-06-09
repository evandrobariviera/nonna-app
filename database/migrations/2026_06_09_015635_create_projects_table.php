<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('macro_plan_id')->constrained('macro_plans')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();

            $table->unsignedSmallInteger('position')->default(1);
            $table->string('title', 200);
            $table->text('objective')->nullable();
            $table->jsonb('disciplines')->nullable(); // ["criacao","web","trafego","setup","social","seo","email"]

            // Briefings por head
            $table->text('briefing_criacao')->nullable();
            $table->text('briefing_web')->nullable();
            $table->text('briefing_trafego')->nullable();
            $table->text('briefing_setup')->nullable();
            $table->text('briefing_social')->nullable();
            $table->text('briefing_seo')->nullable();
            $table->text('briefing_email')->nullable();

            $table->string('status', 20)->default('draft'); // draft | active | completed | cancelled

            $table->string('clickup_task_id', 100)->nullable();
            $table->timestamp('launched_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('projects');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('meetings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('title', 200);
            $table->string('type', 50);
            $table->string('modality', 30)->default('online');
            $table->string('status', 30)->default('para_agendar');

            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();

            $table->foreignId('organized_by')->constrained('users');
            $table->foreignId('created_by')->constrained('users');

            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            $table->string('location', 255)->nullable();
            $table->string('online_link', 500)->nullable();

            $table->text('agenda')->nullable();
            $table->text('ata')->nullable();
            $table->text('next_steps')->nullable();

            $table->timestamp('ata_recorded_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('meetings');
    }
};

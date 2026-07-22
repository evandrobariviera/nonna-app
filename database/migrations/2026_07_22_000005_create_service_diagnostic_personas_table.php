<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('service_diagnostic_personas', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('diagnostic_id')->constrained('service_diagnostics')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);

            $table->string('tag', 20)->nullable();  // "Persona A", "Persona B"...
            $table->string('name', 150);
            $table->text('profile')->nullable();
            $table->text('behavior')->nullable();
            $table->text('evidence')->nullable(); // trecho/exemplo real observado na conversa

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('service_diagnostic_personas');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Central de Ajuda — wiki interno de processos. Qualquer usuário interno pode
        // criar/editar (estilo Wikipedia, sem aprovação) — updated_by é só "quem editou
        // por último", não um histórico de versões completo.
        Schema::connection('pgsql')->create('help_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');

            $table->string('title');
            $table->string('slug');
            $table->string('category', 100)->nullable();
            $table->longText('body')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('help_articles');
    }
};

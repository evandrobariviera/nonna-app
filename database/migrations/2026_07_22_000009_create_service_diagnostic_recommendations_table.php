<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('service_diagnostic_recommendations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('diagnostic_id')->constrained('service_diagnostics')->cascadeOnDelete();

            $table->string('category', 20); // quick_win | estrutural
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pendente'); // pendente | implementada | rejeitada
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedSmallInteger('position')->default(1);

            // Encadeia a mesma recomendação entre versões, pra montar o loop causa-efeito
            // ("recomendado na v1, ainda pendente na v2, implementado na v3 -> resultado: X")
            $table->uuid('previous_recommendation_id')->nullable();

            $table->timestamps();
        });

        // FK auto-referenciada precisa vir depois da criação da tabela (Postgres não
        // aceita a constraint inline no mesmo CREATE TABLE que a própria PK referenciada).
        Schema::connection('pgsql')->table('service_diagnostic_recommendations', function (Blueprint $table) {
            $table->foreign('previous_recommendation_id')
                ->references('id')->on('service_diagnostic_recommendations')->nullOnDelete();
        });

        // A narrativa de recomendações agora é relacional (permite rastrear status entre
        // versões) - o que estava em JSONB vira registros nesta tabela.
        Schema::connection('pgsql')->table('service_diagnostics', function (Blueprint $table) {
            $table->dropColumn('recommendations');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('service_diagnostics', function (Blueprint $table) {
            $table->jsonb('recommendations')->nullable();
        });

        Schema::connection('pgsql')->dropIfExists('service_diagnostic_recommendations');
    }
};

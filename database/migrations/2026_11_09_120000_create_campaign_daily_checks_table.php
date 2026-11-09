<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conferência diária da Central de Campanhas: o checklist de 5 minutos que
 * cada gestor marca por dia ("todas gastaram ontem?", "algum reprovado?"...).
 * Uma linha por gestor por dia; os itens marcados ficam em `items`.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('campaign_daily_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('check_date');
            $table->jsonb('items')->default('[]');
            $table->timestamps();

            $table->unique(['user_id', 'check_date']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('campaign_daily_checks');
    }
};

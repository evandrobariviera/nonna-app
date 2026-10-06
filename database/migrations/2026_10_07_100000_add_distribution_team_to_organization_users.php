<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Quem cada pessoa acompanha na grade do modo Distribuição (ids de usuário). null =
    // automático (setores + quem executa tarefas dela) — ver DistributionCockpit::team().
    public function up(): void
    {
        Schema::connection('pgsql')->table('organization_users', function (Blueprint $table) {
            $table->jsonb('distribution_team')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('organization_users', function (Blueprint $table) {
            $table->dropColumn('distribution_team');
        });
    }
};

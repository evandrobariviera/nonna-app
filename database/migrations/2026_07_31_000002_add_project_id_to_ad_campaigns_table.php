<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // Correlaciona a campanha (sincronizada do Meta/Google, com gasto/performance
    // real) com o Projeto interno (planejamento/execução, type=campanha) que a
    // originou — mesmo princípio de tasks.project_id. Vínculo manual: a campanha
    // chega via sync automático, ninguém sabe de qual projeto ela veio até alguém
    // ligar os pontos.
    public function up(): void
    {
        Schema::connection('pgsql')->table('ad_campaigns', function (Blueprint $table) {
            $table->foreignUuid('project_id')->nullable()->after('client_ad_account_id')
                ->constrained('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('ad_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('task_approval_rounds', function (Blueprint $table) {
            // aprovacao | aviso — aviso é retorno informativo (sem entregável), não pede decisão do cliente
            $table->string('type', 20)->default('aprovacao')->after('round_number');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_approval_rounds', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

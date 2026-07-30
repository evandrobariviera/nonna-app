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
            // Snapshot da legenda no momento do envio — igual aos anexos (round_number),
            // a rodada guarda o texto exato avaliado, mesmo que a legenda mude depois.
            $table->text('caption')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_approval_rounds', function (Blueprint $table) {
            $table->dropColumn('caption');
        });
    }
};

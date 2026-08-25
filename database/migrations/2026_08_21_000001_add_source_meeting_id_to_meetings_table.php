<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('meetings', function (Blueprint $table) {
            // Liga a Reunião Interna à reunião com o cliente que a originou (pauta gerada
            // por IA a partir da ATA dessa reunião) — self-reference, sem depender de
            // macro_plan_id (que agora só é preenchido manualmente, depois, e não guarda
            // essa relação 1:1 entre as duas reuniões).
            $table->foreignUuid('source_meeting_id')->nullable()->after('macro_plan_id')
                ->constrained('meetings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_meeting_id');
        });
    }
};

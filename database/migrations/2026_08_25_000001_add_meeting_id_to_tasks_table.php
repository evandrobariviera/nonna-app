<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            // Tarefa nascida direto de uma Reunião (ata/próximos passos), antes até de
            // existir Planejamento — origem permanente, mesmo depois que a reunião entrar
            // num Macroplanejamento (MeetingObserver sincroniza macro_plan_id nesse caso).
            $table->foreignUuid('meeting_id')->nullable()->after('macro_plan_id')
                ->constrained('meetings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meeting_id');
        });
    }
};

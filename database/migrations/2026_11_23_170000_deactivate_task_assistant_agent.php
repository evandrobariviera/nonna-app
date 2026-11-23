<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // 23/11 (Evandro): o único agente da Nonna é o universal (Agente Nonna). O Assistente de
    // Lançamento de Tarefas sai da página da campanha/projeto e o agente fica inativo
    // (registro mantido: histórico de uso e custo continua apontando pra ele).
    public function up(): void
    {
        DB::connection('pgsql')->table('ai_agents')->where('slug', 'task-assistant')->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('ai_agents')->where('slug', 'task-assistant')->update(['is_active' => true, 'updated_at' => now()]);
    }
};

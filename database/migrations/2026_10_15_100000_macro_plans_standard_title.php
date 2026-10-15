<?php

use App\Models\Client;
use App\Models\MacroPlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nome padrão e imutável dos planejamentos (pedido do Evandro em 2026-10-15 — "cada um cria
 * de um jeito"): "Planejamento {apelido} · 09/26 a 12/26", gerado pelo MacroPlan::saving().
 *
 * O título antigo vai pra `focus` quando dizia o ASSUNTO ("Expointer Gomes", "Calendário
 * editorial Franqueados"). Os que só repetiam cliente + período ("Casa Rigotti — Planejamento
 * 09/2026") não viram foco — seria a mesma informação do nome novo, duplicada.
 * Escrita direta no banco: não é edição de ninguém, não entra no histórico do planejamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('macro_plans', function (Blueprint $table) {
            $table->string('focus', 200)->nullable()->after('title');
        });

        $clients = Client::withoutGlobalScopes()->get()->keyBy('id');

        DB::table('macro_plans')->orderBy('created_at')->get(['id', 'client_id', 'title', 'period_start', 'period_end'])
            ->each(function ($plan) use ($clients) {
                $old = trim((string) $plan->title);
                $focus = ($old === '' || mb_stripos($old, 'planejamento') !== false) ? null : $old;

                DB::table('macro_plans')->where('id', $plan->id)->update([
                    'focus' => $focus,
                    'title' => MacroPlan::standardTitleFor(
                        $clients->get($plan->client_id),
                        $plan->period_start ? Carbon::parse($plan->period_start) : null,
                        $plan->period_end ? Carbon::parse($plan->period_end) : null,
                    ),
                ]);
            });
    }

    public function down(): void
    {
        // Volta o assunto pro título quando havia; os demais ficam com o nome padrão.
        DB::table('macro_plans')->whereNotNull('focus')->update(['title' => DB::raw('focus')]);

        Schema::table('macro_plans', function (Blueprint $table) {
            $table->dropColumn('focus');
        });
    }
};

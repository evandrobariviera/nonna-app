<?php

use App\Models\MacroPlan;
use Illuminate\Database\Migrations\Migration;

/**
 * O agente finalize_macro_meeting às vezes gravou campos de texto dos blocos
 * (visto em bloco5.pendencias) como lista de strings em vez de texto. A tela
 * de edição do planejamento faz htmlspecialchars() nesse valor → erro 500.
 *
 * Normaliza todos os blocos existentes via MacroPlan::flattenBlockText()
 * (lista de escalares → linhas; estruturas com sub-objetos ficam intactas).
 * A partir daqui o AutomationJob já grava normalizado.
 */
return new class extends Migration
{
    public function up(): void
    {
        MacroPlan::withoutGlobalScopes()->get()->each(function (MacroPlan $mp) {
            $dirty = [];
            foreach (['bloco1', 'bloco2', 'bloco4', 'bloco5'] as $block) {
                $original   = $mp->{$block};
                $normalized = MacroPlan::flattenBlockText($original);
                if (is_array($original) && $normalized !== $original) {
                    $dirty[$block] = $normalized;
                }
            }
            if ($dirty) {
                $mp->forceFill($dirty)->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // Sem volta — a lista original não é recuperável do texto juntado.
    }
};

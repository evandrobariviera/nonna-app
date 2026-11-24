<?php

namespace App\Http\Controllers;

use App\Models\MacroPlan;
use App\Models\MacroPlanCheckin;
use App\Models\ProjectCheckin;
use App\Services\Documents\DocumentEditor;
use App\Services\PendencyService;
use App\Services\Projects\CheckinSystem;
use App\Services\Projects\PlanCheckinSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Conferência das METAS do planejamento (aba Acompanhamento do planejamento, 24/11). O
 * responsável avalia cada meta do ciclo vendo as campanhas/projetos que ajudam nela; no
 * fechamento, o resultado de cada meta vai para a seção Fechamento do documento. Sem IA.
 */
class MacroPlanCheckinController extends Controller
{
    public function show(MacroPlan $macroplan)
    {
        return view('macroplans._checkins', ['macroplan' => $macroplan]);
    }

    public function store(Request $request, MacroPlan $macroplan, PlanCheckinSystem $system, PendencyService $pendencies)
    {
        $data = $request->validate([
            'situacao'           => ['required', Rule::in(array_keys(ProjectCheckin::SITUACOES))],
            'proximo_passo'      => ['required', 'string', 'max:2000'],
            'travou'             => ['nullable', 'string', 'max:2000'],
            'depende'            => ['nullable', Rule::in(array_keys(ProjectCheckin::DEPENDE))],
            'metas'              => ['array'],
            'metas.*.atual'      => ['nullable', 'string', 'max:120'],
            'metas.*.avaliacao'  => ['nullable', Rule::in(array_keys(ProjectCheckin::AVALIACOES))],
            'metas.*.comentario' => ['nullable', 'string', 'max:1000'],
            'metas.*.resultado'  => ['nullable', Rule::in(array_keys(MacroPlanCheckin::RESULTADOS))],
            'fechar'             => ['nullable', 'boolean'],
            'fech'               => ['array'],
            'fech.*'             => ['nullable', 'string', 'max:8000'],
        ], [
            'proximo_passo.required' => 'Escreva o próximo passo.',
            'situacao.required'      => 'Escolha a situação geral.',
        ]);

        $sys     = $system->build($macroplan);
        $closing = (bool) ($data['fechar'] ?? false) && !$sys['fechada'];

        $metas = collect($sys['metas'])->map(function ($m) use ($data, $closing) {
            $in    = $data['metas'][$m['key']] ?? [];
            $atual = trim((string) ($in['atual'] ?? '')) ?: null;

            return [
                'key'        => $m['key'],
                'nome'       => $m['nome'],
                'partida'    => $m['partida'],
                'meta'       => $m['meta'],
                'atual'      => $atual,
                'semaforo'   => CheckinSystem::light($m['partida'], $m['meta'], $atual, $m['ritmo']),
                'avaliacao'  => $in['avaliacao'] ?? null,
                'comentario' => trim((string) ($in['comentario'] ?? '')) ?: null,
                'resultado'  => $closing ? ($in['resultado'] ?? null) : null,
            ];
        })->values()->all();

        // Foto das campanhas/projetos de cada meta no dia (para o histórico e o agente).
        $foto = collect($sys['metas'])->mapWithKeys(fn ($m) => [$m['key'] => collect($m['quem'])->map(fn ($q) => [
            'nome' => $q['nome'], 'situacao' => $q['conferencia']['situacao'] ?? null,
        ])->all()])->all();

        $macroplan->checkins()->create([
            'organization_id' => $macroplan->organization_id,
            'user_id'         => Auth::id(),
            'checked_on'      => today(),
            'moment'          => $closing ? 'fechamento' : ($sys['momento'] === 'meio' && $sys['proxima'] && $sys['proxima'] <= today()->toDateString() ? 'meio' : 'mensal'),
            'situacao'        => $data['situacao'],
            'metas'           => $metas,
            'travou'          => trim((string) ($data['travou'] ?? '')) ?: null,
            'depende'         => $data['depende'] ?? null,
            'proximo_passo'   => trim($data['proximo_passo']),
            'sistema'         => ['ritmo' => $sys['ritmo'], 'quem' => $foto],
            'is_closing'      => $closing,
        ]);

        // Fechamento: resultado de cada meta + o que aprendemos/levamos vão para o documento.
        if ($closing && ($doc = $macroplan->fieldDocument()->first())) {
            $editor = app(DocumentEditor::class);
            $lines  = collect($metas)->map(fn ($m) => $m['nome'] . ': '
                . (MacroPlanCheckin::RESULTADOS[$m['resultado']] ?? 'sem resultado marcado')
                . ($m['atual'] ? " — terminou em {$m['atual']}" : '') . ($m['meta'] ? " (queríamos {$m['meta']})" : '')
                . ($m['comentario'] ? ". {$m['comentario']}" : ''))->implode("\n");
            if ($lines !== '') {
                $editor->setField($doc, 'resultado_metas', $lines, Auth::id());
            }
            foreach ($sys['fech_fields'] as $f) {
                if (array_key_exists($f['key'], $data['fech'] ?? [])) {
                    $editor->setField($doc, $f['key'], $data['fech'][$f['key']], Auth::id());
                }
            }
        }

        $open = $macroplan->pendencies()->open()
            ->where(fn ($w) => collect(MacroPlanCheckin::PENDENCY_PREFIXES)->each(fn ($p) => $w->orWhere('title', 'like', $p . '%')))->get();
        foreach ($open as $pendency) {
            $pendencies->resolve($pendency, Auth::id(), $closing ? 'Fechamento do ciclo registrado.' : 'Conferência das metas registrada.');
        }

        return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => 'acompanhamento'])
            ->with('success', $closing ? 'Fechamento do ciclo registrado.' : 'Conferência das metas registrada.');
    }

    public function destroy(MacroPlan $macroplan, MacroPlanCheckin $checkin)
    {
        abort_unless($checkin->macro_plan_id === $macroplan->id && $checkin->user_id === Auth::id()
            && $checkin->created_at->gt(now()->subDay()), 403);
        $checkin->delete();

        return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => 'acompanhamento'])->with('success', 'Conferência apagada.');
    }
}

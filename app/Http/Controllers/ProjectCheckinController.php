<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectCheckin;
use App\Services\Documents\DocumentEditor;
use App\Services\PendencyService;
use App\Services\Projects\CheckinSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Conferência da campanha/projeto (aba Acompanhamento, etapa E.2 — 24/11). O coordenador
 * avalia as metas, os pontos de cuidado e o andamento; o pilar Sistema é calculado no servidor
 * (CheckinSystem) e guardado junto, como foto do dia. Sem IA.
 */
class ProjectCheckinController extends Controller
{
    /** Só o conteúdo da aba (carregado quando a pessoa abre a aba). */
    public function show(Project $project)
    {
        return view('projects._checkins', ['project' => $project]);
    }

    public function store(Request $request, Project $project, CheckinSystem $system, PendencyService $pendencies)
    {
        $data = $request->validate([
            'situacao'              => ['required', Rule::in(array_keys(ProjectCheckin::SITUACOES))],
            'proximo_passo'         => ['required', 'string', 'max:2000'],
            'travou'                => ['nullable', 'string', 'max:2000'],
            'depende'               => ['nullable', Rule::in(array_keys(ProjectCheckin::DEPENDE))],
            'metas'                 => ['array'],
            'metas.*.atual'         => ['nullable', 'string', 'max:120'],
            'metas.*.avaliacao'     => ['nullable', Rule::in(array_keys(ProjectCheckin::AVALIACOES))],
            'metas.*.comentario'    => ['nullable', 'string', 'max:1000'],
            'cuidados'              => ['array'],
            'cuidados.*.aconteceu'  => ['nullable', Rule::in(['sim', 'nao'])],
            'cuidados.*.combinado'  => ['nullable', Rule::in(array_keys(ProjectCheckin::COMBINADO))],
            'fechar'                => ['nullable', 'boolean'],
            'fech'                  => ['array'],
            'fech.*'                => ['nullable', 'string', 'max:8000'],
        ], [
            'proximo_passo.required' => 'Escreva o próximo passo.',
            'situacao.required'      => 'Escolha a situação geral.',
        ]);

        $sys = $system->build($project);

        // As linhas vêm do documento (o que o Sistema montou); da tela só entra a avaliação.
        $metas = collect($sys['metas'])->map(function ($m) use ($data, $sys) {
            $in    = $data['metas'][$m['key']] ?? [];
            $atual = $m['key'] === 'entrega' ? null : (trim((string) ($in['atual'] ?? '')) ?: null);

            return [
                'key'        => $m['key'],
                'nome'       => $m['nome'],
                'partida'    => $m['partida'],
                'meta'       => $m['meta'],
                'atual'      => $atual,
                'semaforo'   => CheckinSystem::light($m['partida'], $m['meta'], $atual, $sys['ritmo']),
                'avaliacao'  => $in['avaliacao'] ?? null,
                'comentario' => trim((string) ($in['comentario'] ?? '')) ?: null,
            ];
        })->values()->all();

        $cuidados = collect($sys['cuidados'])->map(function ($c) use ($data) {
            $in = $data['cuidados'][$c['id']] ?? [];
            $aconteceu = $in['aconteceu'] ?? null;

            return [
                'id'        => $c['id'],
                'se'        => $c['se'],
                'fazemos'   => $c['fazemos'],
                'aconteceu' => $aconteceu,
                'combinado' => $aconteceu === 'sim' ? ($in['combinado'] ?? null) : null,
            ];
        })->values()->all();

        $project->checkins()->create([
            'organization_id' => $project->organization_id,
            'user_id'         => Auth::id(),
            'checked_on'      => today(),
            'situacao'        => $data['situacao'],
            'metas'           => $metas,
            'cuidados'        => $cuidados,
            'travou'          => trim((string) ($data['travou'] ?? '')) ?: null,
            'depende'         => $data['depende'] ?? null,
            'proximo_passo'   => trim($data['proximo_passo']),
            'sistema'         => ['ritmo' => $sys['ritmo'], 'andamento' => $sys['andamento'], 'anuncios' => $sys['anuncios']],
            'is_closing'      => $closing = (bool) ($data['fechar'] ?? false) && !$sys['fechada'],
        ]);

        // Fechamento: o que foi escrito na conferência vai para a seção "Fechamento" do documento
        // (o documento continua sendo a fonte — é ele que o próximo planejamento lê).
        if ($closing && ($doc = $project->fieldDocument()->first())) {
            $editor = app(DocumentEditor::class);
            foreach ($sys['fech_fields'] as $f) {
                if (array_key_exists($f['key'], $data['fech'] ?? [])) {
                    $editor->setField($doc, $f['key'], $data['fech'][$f['key']], Auth::id());
                }
            }
        }

        // A pendência automática "Conferir ..." / "Fechar ..." se resolve com a conferência.
        $open = $project->pendencies()->open()
            ->where(fn ($w) => collect(ProjectCheckin::PENDENCY_PREFIXES)->each(fn ($p) => $w->orWhere('title', 'like', $p . ' %')))->get();
        foreach ($open as $pendency) {
            $pendencies->resolve($pendency, Auth::id(), $closing ? 'Fechamento registrado.' : 'Conferência registrada.');
        }

        return redirect()->to($this->url($project))->with('success', $closing ? 'Fechamento registrado.' : 'Conferência registrada.');
    }

    public function destroy(Project $project, ProjectCheckin $checkin)
    {
        abort_unless($checkin->project_id === $project->id && $checkin->user_id === Auth::id()
            && $checkin->created_at->gt(now()->subDay()), 403);
        $checkin->delete();

        return redirect()->to($this->url($project))->with('success', 'Conferência apagada.');
    }

    private function url(Project $project): string
    {
        $base = $project->macro_plan_id
            ? route('macroplans.projects.show', [$project->macro_plan_id, $project])
            : route('projects.showDirect', $project);

        return $base . '?aba=acompanhamento';
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\MacroPlan;
use App\Models\Project;
use App\Services\Documents\DocumentEditor;
use App\Services\Documents\PlanItemsLauncher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Documentos em campos (23/11). Controller fino: toda escrita passa pelo DocumentEditor,
 * que valida contra o modelo do tipo. A tela (documents/_editor) salva campo a campo.
 */
class FieldDocumentController extends Controller
{
    public function __construct(private DocumentEditor $editor) {}

    /** Começa o documento em campos de um planejamento que ainda não tem. */
    public function startForPlan(MacroPlan $macroplan): RedirectResponse
    {
        if (!$macroplan->fieldDocument()->exists()) {
            $this->editor->create('planejamento', $macroplan->client_id, $macroplan->getMorphClass(), $macroplan->id, Auth::id());
        }

        return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => 'documento'])
            ->with('success', 'Documento em campos criado. Preencha seção por seção: cada campo salva sozinho.');
    }

    /** Do documento do planejamento para as campanhas e projetos de verdade (PlanItemsLauncher). */
    public function launchPlanItems(MacroPlan $macroplan, PlanItemsLauncher $launcher): RedirectResponse
    {
        $doc = $macroplan->fieldDocument;
        abort_unless($doc, 404);

        try {
            $result = $launcher->launch($macroplan, $doc, Auth::id());
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => 'documento'])->with('error', $e->getMessage());
        }

        $msg = $result['created']
            ? "{$result['created']} campanha(s)/projeto(s) criados, cada um com o seu documento já preenchido com o que veio do planejamento."
            : 'Nada novo para criar: todas as campanhas e projetos do documento já existem.';

        return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => 'documento'])->with('success', $msg);
    }

    /** Campanha/projeto que ainda não tem documento em campos (ex.: criado antes, com HTML). */
    public function startForProject(Project $project): RedirectResponse
    {
        if (!$project->fieldDocument()->exists()) {
            $kind = $project->type === 'campanha' ? 'campanha' : 'projeto';
            $prefill = array_filter([
                'nome'     => $project->title,
                'objetivo' => $project->objective,
                'inicio'   => $project->start_date?->toDateString(),
                'fim'      => $project->end_date?->toDateString(),
            ] + ($kind === 'campanha' ? ['entrega_pecas' => $project->pieces_due_date?->toDateString(), 'checkin_dias' => 15] : []));
            $this->editor->create($kind, $project->client_id, $project->getMorphClass(), $project->id, Auth::id(), $prefill, 'cadastro');
        }

        $url = $project->macro_plan_id
            ? route('macroplans.projects.show', [$project->macro_plan_id, $project])
            : route('projects.showDirect', $project);

        return redirect($url)->with('success', 'Documento em campos criado. Cada campo salva sozinho.');
    }

    public function field(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'path'  => 'required|string|max:120',
            'value' => 'nullable',
        ]);

        try {
            $document = $this->editor->setField($document, $data['path'], $data['value'] ?? null, Auth::id());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($this->editor->payload($document));
    }

    public function list(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'list'  => 'required|string|max:40',
            'op'    => 'required|in:add,remove,duplicate,up,down,tipo',
            'item'  => 'nullable|string|max:20',
            'attrs' => 'nullable|array',
        ]);

        try {
            $document = $this->editor->listOp($document, $data['list'], $data['op'], $data['item'] ?? null, $data['attrs'] ?? [], Auth::id());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($this->editor->payload($document));
    }

    /** A versão do cliente: o mesmo conteúdo no visual do modelo, sem instruções nem avisos. */
    public function render(Document $document): View
    {
        $schema = $document->schema();

        return view($schema->clientView(), [
            'document' => $document,
            'content'  => $document->content,
            'schema'   => $schema,
            'owner'    => $document->documentable,
        ]);
    }
}

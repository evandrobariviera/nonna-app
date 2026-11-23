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

    /**
     * Rascunho do planejamento pelo Agente Nonna (PlanningDrafter, em segundo plano). Arquivos
     * enviados na hora ficam como anexos do planejamento e entram como fonte.
     */
    public function startDraft(Request $request, MacroPlan $macroplan): RedirectResponse
    {
        $doc = $macroplan->fieldDocument;
        abort_unless($doc, 404);
        $back = redirect()->route('macroplans.edit', [$macroplan, 'bloco' => 'documento']);

        $data = $request->validate([
            'sources'   => 'nullable|array|max:40',
            'sources.*' => 'string|max:80',
            'note'      => 'nullable|string|max:3000',
            'files'     => 'nullable|array|max:6',
            'files.*'   => 'file|max:15360|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,csv,txt,md,html,htm,png,jpg,jpeg,webp',
        ], ['files.*.mimes' => 'Arquivo de tipo que o agente não lê.', 'files.*.max' => 'Cada arquivo pode ter até 15 MB.']);

        if ($this->editor->touchedByPerson($doc)) {
            return $back->with('error', 'Este documento já foi editado: o rascunho do agente só entra em documento ainda não preenchido.');
        }
        if (($doc->agent_state['draft']['status'] ?? null) === 'processando') {
            return $back->with('error', 'O agente já está montando o rascunho deste planejamento.');
        }
        if (empty($data['sources']) && !$request->hasFile('files')) {
            return $back->with('error', 'Marque pelo menos uma fonte ou envie um arquivo.');
        }
        $credits = \App\Services\Agent\AgentCredits::status($request->user());
        if ($credits['blocked']) {
            return $back->with('error', \App\Services\Agent\AgentCredits::blockedMessage($credits));
        }

        $uploadIds = [];
        foreach ($request->file('files', []) as $file) {
            $disk = config('filesystems.default', 'r2');
            $mime = $file->getMimeType();
            $path = $file->store("macroplans/{$macroplan->id}", \App\Support\UploadOptions::forStore($mime, $disk));
            $uploadIds[] = \App\Models\MacroPlanAttachment::create([
                'macro_plan_id' => $macroplan->id, 'filename' => $file->getClientOriginalName(), 'disk_path' => $path,
                'disk' => $disk, 'mime_type' => $mime, 'size' => $file->getSize(), 'uploaded_by' => Auth::id(),
            ])->id;
        }

        $state = $doc->agent_state ?? [];
        $state['draft'] = ['status' => 'processando', 'started_at' => now()->toIso8601String(), 'user_id' => Auth::id()];
        $doc->update(['agent_state' => $state]);

        \App\Jobs\DraftPlanningDocumentJob::dispatch($doc->id, $macroplan->id, $data['sources'] ?? [], $uploadIds, Auth::id(), (string) ($data['note'] ?? ''));

        return $back->with('success', 'O agente começou a montar o rascunho. Leva de 1 a 3 minutos; a página atualiza sozinha e você recebe aviso no sino.');
    }

    public function draftStatus(MacroPlan $macroplan): JsonResponse
    {
        return response()->json(['status' => $macroplan->fieldDocument?->agent_state['draft']['status'] ?? null]);
    }

    public function hideQuestions(Document $document): RedirectResponse
    {
        $state = $document->agent_state ?? [];
        $state['perguntas_ocultas'] = true;
        $document->update(['agent_state' => $state]);

        return back();
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

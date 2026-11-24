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

    /** Rascunho da campanha/projeto pelo agente (DocumentDrafter: completa só o que está vazio). */
    public function startItemDraft(Request $request, Project $project): RedirectResponse
    {
        $doc = $project->fieldDocument;
        abort_unless($doc, 404);
        $back = redirect($project->macro_plan_id ? route('macroplans.projects.show', [$project->macro_plan_id, $project]) : route('projects.showDirect', $project));

        $data = $request->validate([
            'sources'   => 'nullable|array|max:40',
            'sources.*' => 'string|max:80',
            'note'      => 'nullable|string|max:3000',
            'files'     => 'nullable|array|max:6',
            'files.*'   => 'file|max:15360|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,csv,txt,md,html,htm,png,jpg,jpeg,webp',
        ], ['files.*.mimes' => 'Arquivo de tipo que o agente não lê.', 'files.*.max' => 'Cada arquivo pode ter até 15 MB.']);

        if (($doc->agent_state['draft']['status'] ?? null) === 'processando') {
            return $back->with('error', 'O agente já está montando o rascunho.');
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
            $path = $file->store("projects/{$project->id}", \App\Support\UploadOptions::forStore($mime, $disk));
            $uploadIds[] = \App\Models\ProjectAttachment::create([
                'project_id' => $project->id, 'filename' => $file->getClientOriginalName(), 'disk_path' => $path,
                'disk' => $disk, 'mime_type' => $mime, 'size' => $file->getSize(), 'uploaded_by' => Auth::id(),
            ])->id;
        }

        $state = $doc->agent_state ?? [];
        $state['draft'] = ['status' => 'processando', 'started_at' => now()->toIso8601String(), 'user_id' => Auth::id()];
        $doc->update(['agent_state' => $state]);

        \App\Jobs\DraftItemDocumentJob::dispatch($doc->id, $project->id, $data['sources'] ?? [], $uploadIds, Auth::id(), (string) ($data['note'] ?? ''));

        return $back->with('success', 'O agente começou a montar o rascunho. Leva de 1 a 3 minutos; a página atualiza sozinha e você recebe aviso no sino.');
    }

    public function itemDraftStatus(Project $project): JsonResponse
    {
        return response()->json(['status' => $project->fieldDocument?->agent_state['draft']['status'] ?? null]);
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
                'checkin_dias' => 15,
                'coordenador'  => $project->coordinator_id,
            ] + ($kind === 'campanha' ? ['entrega_pecas' => $project->pieces_due_date?->toDateString()] : []));
            $this->editor->create($kind, $project->client_id, $project->getMorphClass(), $project->id, Auth::id(), $prefill, 'cadastro');
        }

        $url = $project->macro_plan_id
            ? route('macroplans.projects.show', [$project->macro_plan_id, $project])
            : route('projects.showDirect', $project);

        return redirect($url)->with('success', 'Documento em campos criado. Cada campo salva sozinho.');
    }

    /** Pergunta ao Agente Nonna ao lado do documento. */
    public function ask(Request $request, Document $document, \App\Services\Documents\DocumentAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'message'    => 'required|string|max:4000',
            'meeting_id' => 'nullable|uuid',
        ]);
        $credits = \App\Services\Agent\AgentCredits::status($request->user());
        if ($credits['blocked']) {
            return response()->json(['error' => \App\Services\Agent\AgentCredits::blockedMessage($credits)], 422);
        }

        try {
            $result = $assistant->ask($document, $request->user(), $data['message'], $data['meeting_id'] ?? null);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'O agente não conseguiu responder agora: ' . \Illuminate\Support\Str::limit($e->getMessage(), 200)], 422);
        }

        return response()->json($result);
    }

    /** Pesquisa de mercado pedida na coluna (só campanha): roda em segundo plano (ResearchDocumentJob). */
    public function startResearch(Request $request, Document $document): JsonResponse
    {
        $project = $document->documentable;
        abort_unless($document->kind === 'campanha' && $project instanceof Project, 404);
        $credits = \App\Services\Agent\AgentCredits::status($request->user());
        if ($credits['blocked']) {
            return response()->json(['error' => \App\Services\Agent\AgentCredits::blockedMessage($credits)], 422);
        }
        $state = $document->agent_state ?? [];
        if (!empty($state['pesquisando']) && now()->diffInMinutes(\Carbon\Carbon::parse($state['pesquisando']), true) < 10) {
            return response()->json(['status' => 'pesquisando']);
        }
        $state['pesquisando'] = now()->toIso8601String();
        $document->update(['agent_state' => $state]);
        \App\Jobs\ResearchDocumentJob::dispatch($document->id, $project->id, (int) Auth::id());

        return response()->json(['status' => 'pesquisando']);
    }

    public function researchStatus(Document $document): JsonResponse
    {
        $state = $document->fresh()->agent_state ?? [];

        return response()->json([
            'status' => !empty($state['pesquisando']) ? 'pesquisando' : (!empty($state['pesquisa']) ? 'pronta' : 'nenhuma'),
            'at'     => $state['pesquisa']['at'] ?? null,
        ]);
    }

    /** Aceitar ou descartar uma proposta do agente. */
    public function decide(Request $request, Document $document, string $suggestion, \App\Services\Documents\DocumentAssistant $assistant): JsonResponse
    {
        $data = $request->validate(['accept' => 'required|boolean']);

        try {
            $result = $assistant->decide($document, $suggestion, (bool) $data['accept'], $request->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($this->editor->payload($result['document']) + ['chat' => $result['chat'], 'skipped' => $result['skipped']]);
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

    /**
     * Peças da campanha / entregas do projeto viram tarefas (DocumentTasks, etapa D). A tela manda
     * as linhas escolhidas, já conferidas pela pessoa (título, tipo, data, quem faz).
     */
    public function createTasks(Request $request, Project $project, \App\Services\Documents\DocumentTasks $service): JsonResponse
    {
        $doc = $project->fieldDocument;
        abort_unless($doc, 404);

        $data = $request->validate([
            'rows'                       => 'required|array|min:1|max:60',
            'rows.*.item_id'             => 'nullable|string|max:16',
            'rows.*.title'               => 'required|string|max:300',
            'rows.*.description'         => 'nullable|string|max:20000',
            'rows.*.task_type'           => 'required|in:' . implode(',', array_keys(\App\Models\Task::$types)),
            'rows.*.due_date'            => 'nullable|date_format:Y-m-d',
            'rows.*.responsavel_user_id' => 'nullable|integer',
            'rows.*.executor_user_id'    => 'nullable|integer',
        ], ['rows.*.task_type.required' => 'Escolha o tipo de cada tarefa.', 'rows.*.title.required' => 'Dê um título a cada tarefa marcada.']);

        return response()->json($service->create($doc, $project, $data['rows'], Auth::id()));
    }

    /** Dispensa / traz de volta uma linha sugerida em "Peças viram tarefas". */
    public function dismissTaskRow(Request $request, Project $project, \App\Services\Documents\DocumentTasks $service): JsonResponse
    {
        $doc = $project->fieldDocument;
        abort_unless($doc, 404);
        $data = $request->validate(['item_id' => 'required|string|max:16', 'dispensar' => 'required|boolean']);
        $service->dismiss($doc, $data['item_id'], (bool) $data['dispensar']);

        return response()->json(['ok' => true]);
    }
}

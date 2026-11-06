<?php

namespace App\Http\Controllers;

use App\Models\DocumentUpdateRun;
use App\Models\MacroPlan;
use App\Models\Meeting;
use App\Models\Project;
use App\Services\DocumentEditService;
use App\Services\DocumentUpdate\DocumentUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

// "Atualizar documento com reunião" — escolher a reunião, acompanhar a análise da IA e
// aplicar as sugestões aceitas (ver DocumentUpdateService).
class DocumentUpdateController extends Controller
{
    public function __construct(private DocumentUpdateService $updates) {}

    public function startPlan(Request $request, MacroPlan $macroplan)
    {
        return $this->startPage($request, $macroplan->load('client'), route('macroplans.document.update-store', $macroplan),
            route('macroplans.edit', [$macroplan, 'bloco' => 'documento']));
    }

    public function startProject(Request $request, Project $project)
    {
        return $this->startPage($request, $project->load('client', 'macroPlan.client'), route('projects.document.update-store', $project),
            $this->projectUrl($project));
    }

    public function storePlan(Request $request, MacroPlan $macroplan)
    {
        return $this->store($request, $macroplan);
    }

    public function storeProject(Request $request, Project $project)
    {
        return $this->store($request, $project);
    }

    /** Pela reunião: lista os documentos do cliente que ela pode atualizar. */
    public function fromMeeting(Meeting $meeting)
    {
        $meeting->load('client');

        return view('documents.update-from-meeting', [
            'meeting' => $meeting,
            'owners'  => $this->updates->ownersFor($meeting),
            // Trava de custo: documentos já conferidos com esta reunião → owner_id => run id
            'usedRuns' => DocumentUpdateService::usedRunsQuery()->where('meeting_id', $meeting->id)->latest()->pluck('id', 'owner_id'),
        ]);
    }

    public function show(DocumentUpdateRun $run)
    {
        $owner = $run->owner() ?? abort(404);
        $run->releaseIfStuck();
        $run->load('meeting', 'requestedBy:id,name', 'appliedBy:id,name');

        return view('documents.update-review', [
            'run'       => $run,
            'owner'     => $owner,
            'kindLabel' => DocumentUpdateService::kindLabel($owner),
            'backUrl'   => $this->ownerUrl($owner),
            'editUrl'   => $owner instanceof MacroPlan ? route('macroplans.document.edit', $owner) : route('projects.document.edit', $owner),
            'stale'     => $run->isReady() && $owner->document_attachment_id !== $run->base_attachment_id,
            'dataDiff'  => $this->dataDiff($run, $owner),
        ]);
    }

    /** Campos do bloco de dados que a IA mudou: [[campo, antes, depois]] em texto. */
    private function dataDiff(DocumentUpdateRun $run, MacroPlan|Project $owner): array
    {
        if (!$run->data_after) {
            return [];
        }

        $base = $owner->attachments()->find($run->base_attachment_id);
        $before = $base ? app(DocumentEditService::class)->readData(Storage::disk($base->disk)->get($base->disk_path) ?? '') : [];

        $show = fn ($v) => match (true) {
            $v === null                => '—',
            is_array($v) && array_is_list($v) => collect($v)->map(fn ($i) => is_array($i) ? implode(' · ', array_map(fn ($x) => is_array($x) ? json_encode($x, JSON_UNESCAPED_UNICODE) : (string) $x, array_filter($i, fn ($x) => $x !== null))) : (string) $i)->implode("\n"),
            is_array($v)               => json_encode($v, JSON_UNESCAPED_UNICODE),
            is_bool($v)                => $v ? 'sim' : 'não',
            default                    => (string) $v,
        };

        return collect($run->data_after)
            ->filter(fn ($v, $k) => ($before[$k] ?? null) != $v)
            ->map(fn ($v, $k) => [$k, $show($before[$k] ?? null), $show($v)])
            ->values()
            ->all();
    }

    public function status(DocumentUpdateRun $run)
    {
        $run->releaseIfStuck();

        return response()->json(['status' => $run->status]);
    }

    public function apply(Request $request, DocumentUpdateRun $run)
    {
        $data = $request->validate([
            'accepted'   => 'array',
            'accepted.*' => 'integer',
            'with_data'  => 'nullable|boolean',
        ]);

        try {
            $result = $this->updates->apply($run, array_map('intval', $data['accepted'] ?? []), (bool) ($data['with_data'] ?? false), (int) Auth::id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = $result['applied'] . ' mudança(s) aplicada(s) — a versão anterior do documento ficou guardada.';
        if ($result['changes']) {
            $message .= ' Atualizado no App: ' . implode(' · ', $result['changes']) . '.';
        }
        if ($result['failed']) {
            $message .= ' Não deu para aplicar (o trecho mudou no documento): ' . implode('; ', $result['failed']) . '. Ajuste pelo editor.';
        }

        return redirect()->route('document-updates.show', $run)->with('success', $message);
    }

    /** Salva um comentário da rodada (autosave da tela). Chave: geral | s:{id} | m:{i} | p:{i}. */
    public function comment(Request $request, DocumentUpdateRun $run)
    {
        $data = $this->validateJson($request, [
            'key'  => ['required', 'string', 'regex:/^(geral|[smp]:\d{1,3})$/'],
            'text' => 'nullable|string|max:3000',
        ]);

        try {
            $this->updates->saveComment($run, $data['key'], (string) ($data['text'] ?? ''));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    /** Edição manual do "depois" de uma sugestão. */
    public function editSuggestion(Request $request, DocumentUpdateRun $run, int $suggestion)
    {
        $data = $this->validateJson($request, ['html' => 'present|nullable|string|max:60000']);

        try {
            $readable = $this->updates->editSuggestion($run, $suggestion, (string) ($data['html'] ?? ''));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'readable' => $readable]);
    }

    public function revise(DocumentUpdateRun $run)
    {
        try {
            $this->updates->requestRevision($run);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('document-updates.show', $run);
    }

    // validate() em rota web chamada via fetch vira 302 (bootstrap/app.php) — aqui 422 em JSON.
    private function validateJson(Request $request, array $rules): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['message' => $validator->errors()->first()], 422)
            );
        }

        return $validator->validated();
    }

    public function discard(DocumentUpdateRun $run)
    {
        if ($run->isReady() || $run->status === 'erro') {
            $run->update(['status' => 'descartado']);
        }

        return redirect()->to($this->ownerUrl($run->owner() ?? abort(404)))->with('success', 'Sugestões descartadas — o documento não mudou.');
    }

    private function startPage(Request $request, MacroPlan|Project $owner, string $storeUrl, string $backUrl)
    {
        abort_unless($owner->document_attachment_id, 404, 'Este item ainda não tem documento.');

        return view('documents.update-start', [
            'owner'     => $owner,
            'kindLabel' => DocumentUpdateService::kindLabel($owner),
            'meetings'  => $this->updates->meetingsFor($owner),
            'selected'  => $request->query('reuniao'),
            'storeUrl'  => $storeUrl,
            'backUrl'   => $backUrl,
            // Trava de custo: reuniões já conferidas com este documento → meeting_id => run id
            'usedRuns'  => DocumentUpdateService::usedRunsQuery()
                ->where('owner_type', DocumentUpdateService::ownerType($owner))
                ->where('owner_id', $owner->id)
                ->latest()
                ->pluck('id', 'meeting_id'),
            'recent'    => DocumentUpdateRun::where('owner_type', DocumentUpdateService::ownerType($owner))
                ->where('owner_id', $owner->id)
                ->with('meeting:id,title,scheduled_at', 'requestedBy:id,name')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    private function store(Request $request, MacroPlan|Project $owner)
    {
        $data = $request->validate([
            'meeting_id'  => 'required|uuid',
            'instruction' => 'nullable|string|max:2000',
        ]);

        $meeting = Meeting::findOrFail($data['meeting_id']);

        // Trava de custo: já conferido com esta reunião → abre a análise que existe.
        if ($existing = $this->updates->existingRun($owner, $meeting)) {
            return redirect()->route('document-updates.show', $existing)
                ->with('error', 'Este documento já foi conferido com esta reunião — cada reunião é analisada uma vez só. Esta é a análise feita.');
        }
        // Duplo clique: o segundo envio chega antes do primeiro gravar a análise.
        if (!\Illuminate\Support\Facades\Cache::add('doc-update-start:' . $owner->id . ':' . $meeting->id, Auth::id(), now()->addSeconds(30))) {
            return back()->with('error', 'A conferência deste documento com esta reunião já está sendo criada.');
        }

        try {
            $run = $this->updates->start($owner, $meeting, $data['instruction'] ?? null, (int) Auth::id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('document-updates.show', $run);
    }

    private function ownerUrl(MacroPlan|Project $owner): string
    {
        return $owner instanceof MacroPlan
            ? route('macroplans.edit', [$owner, 'bloco' => 'documento'])
            : $this->projectUrl($owner);
    }

    private function projectUrl(Project $project): string
    {
        return $project->macro_plan_id
            ? route('macroplans.projects.show', [$project->macro_plan_id, $project])
            : route('projects.showDirect', $project);
    }
}

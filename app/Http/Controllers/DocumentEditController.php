<?php

namespace App\Http\Controllers;

use App\Models\MacroPlan;
use App\Models\MacroPlanAttachment;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Services\DocumentEditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

// Editor do documento HTML do planejamento e da campanha/projeto (ver DocumentEditService).
// Salvar chega por fetch (JSON) — validação devolve 422 em JSON, não redirect.
class DocumentEditController extends Controller
{
    public function __construct(private DocumentEditService $documents) {}

    public function editPlan(MacroPlan $macroplan)
    {
        return $this->editor($macroplan->load('client'), 'plan', route('macroplans.edit', [$macroplan, 'bloco' => 'documento']));
    }

    public function editProject(Project $project)
    {
        return $this->editor($project->load('client', 'macroPlan.client'), 'project', $this->projectUrl($project));
    }

    public function savePlan(Request $request, MacroPlan $macroplan)
    {
        return $this->save($request, $macroplan, route('macroplans.edit', [$macroplan, 'bloco' => 'documento']));
    }

    public function saveProject(Request $request, Project $project)
    {
        return $this->save($request, $project, $this->projectUrl($project));
    }

    public function restorePlan(MacroPlan $macroplan, MacroPlanAttachment $version)
    {
        $changes = $this->documents->restore($macroplan, $version, (int) Auth::id());

        return redirect()->route('macroplans.edit', [$macroplan, 'bloco' => 'documento'])
            ->with('success', $this->message('Versão de ' . $version->created_at->format('d/m/Y H:i') . ' restaurada.', $changes));
    }

    public function restoreProject(Project $project, ProjectAttachment $version)
    {
        $changes = $this->documents->restore($project, $version, (int) Auth::id());

        return redirect()->to($this->projectUrl($project))
            ->with('success', $this->message('Versão de ' . $version->created_at->format('d/m/Y H:i') . ' restaurada.', $changes));
    }

    private function editor(MacroPlan|Project $owner, string $kind, string $backUrl)
    {
        [$attachment, $html, $data] = $this->documents->load($owner);

        return view('documents.edit', [
            'owner'      => $owner,
            'kind'       => $kind,
            'attachment' => $attachment,
            'html'       => $html,
            'data'       => $data,
            'versions'   => $this->documents->versions($owner),
            'backUrl'    => $backUrl,
            'saveUrl'    => $kind === 'plan' ? route('macroplans.document.save', $owner) : route('projects.document.save', $owner),
        ]);
    }

    private function save(Request $request, MacroPlan|Project $owner, string $backUrl)
    {
        $validator = Validator::make($request->all(), [
            'html' => 'required|string|max:5000000',
            'data' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $changes = $this->documents->save($owner, $request->input('html'), $request->input('data'), (int) Auth::id());
        session()->flash('success', $this->message('Documento salvo — a versão anterior ficou guardada.', $changes));

        return response()->json(['redirect' => $backUrl]);
    }

    private function message(string $base, array $changes): string
    {
        return $changes ? $base . ' Atualizado no App: ' . implode(' · ', $changes) . '.' : $base;
    }

    private function projectUrl(Project $project): string
    {
        return $project->macro_plan_id
            ? route('macroplans.projects.show', [$project->macro_plan_id, $project])
            : route('projects.showDirect', $project);
    }
}

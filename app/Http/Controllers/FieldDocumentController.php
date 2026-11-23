<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\MacroPlan;
use App\Services\Documents\DocumentEditor;
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

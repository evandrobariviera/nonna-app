<?php

namespace App\Http\Controllers;

use App\Models\DocumentStandard;
use App\Models\HelpArticle;

/**
 * Mapa dos Documentos (Central de Ajuda): os documentos padrão por etapa, a ficha de cada
 * um e o modelo HTML. Só leitura — o conteúdo entra por `php artisan conteudo:publicar`.
 */
class DocumentMapController extends Controller
{
    public function index()
    {
        $documents = DocumentStandard::inMapOrder();
        $byStage = $documents->groupBy('stage');
        $closed = $documents->where('status', 'fechado')->count();

        return view('help.documents.index', compact('documents', 'byStage', 'closed'));
    }

    public function show(DocumentStandard $document)
    {
        $documents = DocumentStandard::inMapOrder();
        $index = $documents->search(fn (DocumentStandard $d) => $d->id === $document->id);
        $previous = $index > 0 ? $documents->get($index - 1) : null;
        $next = $documents->get($index + 1);

        $manual = $document->help_article_slug
            ? HelpArticle::where('slug', $document->help_article_slug)->first()
            : null;

        return view('help.documents.show', compact('document', 'previous', 'next', 'manual'));
    }

    /**
     * O modelo HTML como página própria (abre em nova aba; imprime em PDF). Vai com CSP
     * `sandbox`: roda numa origem isolada, sem acesso à sessão nem aos cookies do App.
     */
    public function template(DocumentStandard $document)
    {
        abort_unless($document->template_html, 404);

        return response($document->template_html, 200, [
            'Content-Type'            => 'text/html; charset=utf-8',
            'Content-Security-Policy' => 'sandbox allow-scripts allow-popups allow-modals',
            'X-Content-Type-Options'  => 'nosniff',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelpArticleController extends Controller
{
    public function index(Request $request)
    {
        $articlesByCategory = $this->filteredArticles($request)
            ->groupBy(fn (HelpArticle $a) => $a->category ?: 'Sem categoria');

        return view('help.index', compact('articlesByCategory'));
    }

    // Fragmento pra busca dinâmica (live-filter.js) — mesmo padrão de ClientController::results().
    public function results(Request $request)
    {
        $articlesByCategory = $this->filteredArticles($request)
            ->groupBy(fn (HelpArticle $a) => $a->category ?: 'Sem categoria');

        return view('help._results', compact('articlesByCategory'));
    }

    private function filteredArticles(Request $request)
    {
        $query = HelpArticle::with('updatedBy')->orderBy('title');

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('title', 'ilike', "%{$term}%")
                  ->orWhere('body', 'ilike', "%{$term}%");
            });
        }

        return $query->get();
    }

    public function create()
    {
        $categories = HelpArticle::whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        return view('help.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'    => 'required|string|max:200',
            'category' => 'nullable|string|max:100',
            'body'     => 'nullable|string',
        ]);

        $organizationId = app('currentOrganization')->id;

        $article = HelpArticle::create([
            ...$data,
            'slug'       => HelpArticle::uniqueSlugFor($data['title'], $organizationId),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('help.show', $article)->with('success', 'Artigo criado.');
    }

    public function show(HelpArticle $article)
    {
        $article->load('createdBy', 'updatedBy');

        return view('help.show', compact('article'));
    }

    public function edit(HelpArticle $article)
    {
        $categories = HelpArticle::whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        return view('help.edit', compact('article', 'categories'));
    }

    public function update(Request $request, HelpArticle $article)
    {
        $data = $request->validate([
            'title'    => 'required|string|max:200',
            'category' => 'nullable|string|max:100',
            'body'     => 'nullable|string',
        ]);

        if ($data['title'] !== $article->title) {
            $data['slug'] = HelpArticle::uniqueSlugFor($data['title'], $article->organization_id, $article->id);
        }

        $article->update([...$data, 'updated_by' => Auth::id()]);

        return redirect()->route('help.show', $article)->with('success', 'Artigo atualizado.');
    }

    public function destroy(HelpArticle $article)
    {
        $article->delete();

        return redirect()->route('help.index')->with('success', 'Artigo removido.');
    }
}

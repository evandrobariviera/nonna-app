<?php

namespace App\Http\Controllers;

use App\Models\AppUpdate;
use App\Support\RichTextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Novidades do App — linha do tempo do que muda no sistema, escrita pra equipe.
 * Escrever é de Admin/Owner; ler é de todo mundo.
 */
class AppUpdateController extends Controller
{
    public function index(Request $request)
    {
        $updates = AppUpdate::with('createdBy')
            ->when(! $this->podeEscrever(), fn ($q) => $q->publicadas())
            ->orderByRaw('coalesce(published_at, created_at) desc')
            ->get();

        // Abrir a tela é o que marca como lido — some o ponto da barra lateral. Guardamos
        // o "visto até" ANTES de exibir, pra tela ainda conseguir destacar o que é novo.
        $user    = Auth::user();
        $vistoAte = $user?->app_updates_seen_at;

        if ($user) {
            $user->forceFill(['app_updates_seen_at' => now()])->saveQuietly();
        }

        $porMes = $updates->groupBy(fn (AppUpdate $u) => ($u->published_at ?? $u->created_at)
            ->locale('pt_BR')->translatedFormat('F \d\e Y'));

        return view('novidades.index', [
            'porMes'       => $porMes,
            'vistoAte'     => $vistoAte,
            'podeEscrever' => $this->podeEscrever(),
            'total'        => $updates->count(),
        ]);
    }

    public function create()
    {
        abort_unless($this->podeEscrever(), 403);

        return view('novidades.create', ['areas' => $this->areas()]);
    }

    public function store(Request $request)
    {
        abort_unless($this->podeEscrever(), 403);

        $update = AppUpdate::create($this->dadosValidados($request) + ['created_by' => Auth::id()]);

        return redirect()->route('app-updates.index')
            ->with('success', $update->published_at ? 'Novidade publicada.' : 'Rascunho salvo.');
    }

    public function edit(AppUpdate $update)
    {
        abort_unless($this->podeEscrever(), 403);

        return view('novidades.edit', ['update' => $update, 'areas' => $this->areas()]);
    }

    public function update(Request $request, AppUpdate $update)
    {
        abort_unless($this->podeEscrever(), 403);

        $update->update($this->dadosValidados($request, $update));

        return redirect()->route('app-updates.index')->with('success', 'Novidade atualizada.');
    }

    public function destroy(AppUpdate $update)
    {
        abort_unless($this->podeEscrever(), 403);

        $update->delete();

        return redirect()->route('app-updates.index')->with('success', 'Novidade removida.');
    }

    private function dadosValidados(Request $request, ?AppUpdate $existente = null): array
    {
        $dados = $request->validate([
            'title'   => 'required|string|max:200',
            'summary' => 'nullable|string|max:400',
            'body'    => 'nullable|string',
            'kind'    => 'required|in:' . implode(',', array_keys(AppUpdate::$kinds)),
            'area'    => 'nullable|string|max:80',
        ]);

        $dados['body'] = RichTextSanitizer::clean($dados['body'] ?? '') ?: null;

        // Publicar é uma escolha explícita: enquanto não for, ninguém além de quem escreve vê.
        // Corrigir o texto de algo já publicado não muda a data — senão um ajuste de vírgula
        // jogaria a entrada pro topo da linha do tempo e reapareceria como nova pra todos.
        $dados['published_at'] = $request->boolean('publicar')
            ? ($existente?->published_at ?? now())
            : null;

        return $dados;
    }

    /** Áreas já usadas, pra sugerir no formulário em vez de inventar nomes novos toda vez. */
    private function areas(): \Illuminate\Support\Collection
    {
        return AppUpdate::whereNotNull('area')->distinct()->orderBy('area')->pluck('area');
    }

    private function podeEscrever(): bool
    {
        return in_array(app('currentOrgRole'), ['owner', 'admin'], true);
    }
}

{{-- Formulário de novidade, compartilhado por criar e editar.
     Incluído com @include('novidades._form', ['update' => $update ?? null, 'areas' => $areas]) --}}
@php $u = $update ?? null; @endphp

<div class="card px-6 py-5">
    <div class="grid gap-4">
        <div>
            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">O QUE MUDOU *</label>
            <input type="text" name="title" value="{{ old('title', $u?->title) }}" required autofocus
                   placeholder="Ex: Agora dá pra mudar a data de várias tarefas de uma vez"
                   class="w-full px-3 py-2.5 text-sm focus:outline-none"
                   style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
            <p class="text-xs mt-1" style="color:var(--muted2)">
                Escreva como você contaria pra alguém do time, não como no código.
            </p>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">TIPO *</label>
                <select name="kind" required
                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    @foreach(\App\Models\AppUpdate::$kinds as $chave => $info)
                        <option value="{{ $chave }}" @selected(old('kind', $u?->kind ?? 'melhoria') === $chave)>{{ $info['label'] }}</option>
                    @endforeach
                </select>
                <p class="text-xs mt-1" style="color:var(--muted2)">
                    Novidade = não existia. Melhoria = já existia e ficou melhor. Correção = estava errado.
                </p>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">ONDE NO APP</label>
                <input type="text" name="area" value="{{ old('area', $u?->area) }}" list="area-options"
                       placeholder="Ex: Tarefas, Clientes, Produção..."
                       class="w-full px-3 py-2.5 text-sm focus:outline-none"
                       style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                <datalist id="area-options">
                    @foreach($areas as $a)<option value="{{ $a }}">@endforeach
                </datalist>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">RESUMO</label>
            <input type="text" name="summary" value="{{ old('summary', $u?->summary) }}" maxlength="400"
                   placeholder="Uma linha explicando pra que serve na prática"
                   class="w-full px-3 py-2.5 text-sm focus:outline-none"
                   style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1" style="color:var(--muted); letter-spacing:.05em">DETALHES (OPCIONAL)</label>
            <x-rich-editor name="body" :value="old('body', $u?->body)" min-height="220px" />
            <p class="text-xs mt-1" style="color:var(--muted2)">
                Passo a passo, o que muda na rotina de quem usa, prints — só se ajudar. A lista mostra isso recolhido.
            </p>
        </div>

        <label class="flex items-center gap-2 text-sm" style="color:var(--text)">
            <input type="checkbox" name="publicar" value="1"
                   @checked(old('publicar', $u ? (bool) $u->published_at : true))>
            Publicar pra equipe agora
            <span class="text-xs" style="color:var(--muted2)">(sem isso, fica como rascunho e só você vê)</span>
        </label>
    </div>
</div>

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">
                <a href="{{ route('macroplans.index') }}"
                   style="color:var(--muted)"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">← Planejamentos</a>
            </p>
            <h1 class="text-xl font-black" style="color:var(--text)">Novo Macroplanejamento</h1>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto">

        @if($errors->any())
            <div class="mb-5 px-4 py-3 text-sm"
                 style="background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.25); color:var(--red)">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('macroplans.store') }}" enctype="multipart/form-data"
              x-data="{ hasDoc: false }">
            @csrf

            <div class="card p-6 space-y-5">

                {{-- Documento (HTML da skill) — opcional. Com ele, o App lê período, verba,
                     campanhas e projetos e já cria tudo (MacroPlanDocumentService). --}}
                <div class="p-4" style="background:var(--s2); border:1px dashed var(--border2); border-radius:10px">
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">
                        Documento do planejamento <span class="normal-case tracking-normal" style="color:var(--muted2)">(HTML da skill — opcional)</span>
                    </label>
                    <p class="text-xs mb-3" style="color:var(--muted2)">Enviando o HTML, o App já lê o período, a verba e cria as campanhas e projetos. Sem ele, o planejamento nasce vazio e o documento pode ser enviado depois.</p>
                    <input type="file" name="document" accept=".html,.htm"
                           @change="hasDoc = $event.target.files.length > 0"
                           class="w-full text-sm" style="color:var(--text)">
                </div>

                {{-- Cliente --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                        Cliente <span style="color:var(--orange)">*</span>
                    </label>
                    <select name="client_id" required
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        <option value="">Selecione o cliente...</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}"
                                {{ old('client_id', $client?->id) === $c->id ? 'selected' : '' }}>
                                {{ $c->displayName() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Nome é automático (MacroPlan::standardTitleFor): "Planejamento {apelido} ·
                     09/26 a 12/26". Aqui só o Foco, opcional, pra diferenciar planejamentos. --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                        Foco <span class="normal-case tracking-normal" style="color:var(--muted2)">(opcional)</span>
                    </label>
                    <p class="text-xs mb-2" style="color:var(--muted2)">O nome é gerado sozinho: <strong>Planejamento + apelido do cliente + período</strong>. Use o foco só se precisar diferenciar (ex: "Expointer", "Calendário editorial").</p>
                    <input type="text" name="focus" value="{{ old('focus') }}" maxlength="200"
                        placeholder="Ex: Expointer"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)"
                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border)'">
                </div>

                {{-- Período — opcional quando vem o documento (ele traz as datas) --}}
                <p x-show="hasDoc" x-cloak class="text-xs -mb-3" style="color:var(--muted2)">Pode deixar o período em branco — vem do documento. Preenchido aqui, o daqui vale.</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Início do Ciclo <span x-show="!hasDoc" style="color:var(--orange)">*</span>
                        </label>
                        <input type="date" name="period_start" value="{{ old('period_start') }}" :required="!hasDoc"
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Fim do Ciclo <span x-show="!hasDoc" style="color:var(--orange)">*</span>
                        </label>
                        <input type="date" name="period_end" value="{{ old('period_end') }}" :required="!hasDoc"
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    </div>
                </div>

                {{-- Responsável + Status --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Responsável (Estrategista)
                        </label>
                        <select name="responsible_id"
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                            <option value="">— sem responsável —</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ old('responsible_id', auth()->id()) == $u->id ? 'selected' : '' }}>
                                    {{ $u->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Status <span style="color:var(--orange)">*</span>
                        </label>
                        <select name="status" required
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                            @foreach(\App\Models\MacroPlan::$statuses as $key => $s)
                                <option value="{{ $key }}" {{ old('status','draft') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

            </div>

            <div class="flex items-center gap-3 mt-5">
                <button type="submit" class="btn btn-primary">
                    Criar Planejamento →
                </button>
                <a href="{{ route('macroplans.index') }}" class="btn btn-ghost">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</x-app-layout>

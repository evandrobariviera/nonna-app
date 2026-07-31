<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('macroplans.index') }}" class="text-xs font-semibold transition-colors"
               style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">← Planejamentos</a>
            <span style="color:var(--border2)">/</span>
            <span class="text-xs font-semibold" style="color:var(--text)">Importar HTML</span>
        </div>
    </x-slot>

    <div class="max-w-xl">
        <p class="text-xs mb-6" style="color:var(--muted)">
            Envie o HTML do macroplanejamento já aprovado pelo cliente (gerado pela skill de referência). O sistema
            cria o macroplanejamento, os projetos e as campanhas automaticamente a partir do conteúdo do arquivo,
            e guarda o próprio HTML como anexo. Selecione o cliente abaixo — o nome na capa do HTML nem sempre
            sai correto, então não é mais usado pra identificar o cliente automaticamente, só como conferência.
        </p>

        @if(session('error'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold"
                 style="background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.25); color:var(--red)">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('macroplans.import.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                    Cliente <span class="text-[var(--orange)]">*</span>
                </label>
                <select name="client_id" required
                        class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    <option value="">Selecione...</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ old('client_id') === $c->id ? 'selected' : '' }}>{{ $c->displayName() }}</option>
                    @endforeach
                </select>
                @error('client_id')
                    <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                    Arquivo HTML <span class="text-[var(--orange)]">*</span>
                </label>
                <input type="file" name="file" accept=".html,.htm" required
                       class="w-full text-sm"
                       style="color:var(--text)">
                @error('file')
                    <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                        Início do período <span class="text-[var(--orange)]">*</span>
                    </label>
                    <input type="date" name="period_start" value="{{ old('period_start') }}" required
                           class="w-full px-3 py-2.5 text-sm focus:outline-none"
                           style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                </div>
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                        Fim do período <span class="text-[var(--orange)]">*</span>
                    </label>
                    <input type="date" name="period_end" value="{{ old('period_end') }}" required
                           class="w-full px-3 py-2.5 text-sm focus:outline-none"
                           style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                </div>
            </div>

            <p class="text-xs" style="color:var(--muted)">
                O período não é extraído do HTML (vem em texto livre na capa, ex: "Julho a Outubro de 2026") — informe as datas exatas aqui.
            </p>

            <button type="submit"
                    class="px-5 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                    style="background:var(--purple)">
                Importar
            </button>
        </form>
    </div>
</x-app-layout>

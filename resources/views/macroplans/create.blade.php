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

        <form method="POST" action="{{ route('macroplans.store') }}">
            @csrf

            <div class="card p-6 space-y-5">

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

                {{-- Título --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                        Título <span style="color:var(--orange)">*</span>
                    </label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                        placeholder="Ex: Macroplanejamento Q3 2026 — Cliente"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)"
                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border)'">
                </div>

                {{-- Período --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Início do Ciclo <span style="color:var(--orange)">*</span>
                        </label>
                        <input type="date" name="period_start" value="{{ old('period_start') }}" required
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Fim do Ciclo <span style="color:var(--orange)">*</span>
                        </label>
                        <input type="date" name="period_end" value="{{ old('period_end') }}" required
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
                    Criar e Preencher Blocos →
                </button>
                <a href="{{ route('macroplans.index') }}" class="btn btn-ghost">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</x-app-layout>

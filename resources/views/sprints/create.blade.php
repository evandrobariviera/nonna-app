<x-app-layout>
    <x-slot name="header">Nova Sprint</x-slot>

    <div class="max-w-xl mx-auto">
        <div class="card px-6 py-6">
            {{-- Nome não é digitado: é derivado do número + período (ver Sprint::buildTitle).
                 Por isso não existe campo de título aqui — só a pré-visualização abaixo,
                 que acompanha as datas. --}}
            <form method="POST" action="{{ route('sprints.store') }}" class="space-y-5"
                  x-data="{
                    inicio: '{{ old('starts_at', $suggested['starts_at']->format('Y-m-d')) }}',
                    fim:    '{{ old('ends_at', $suggested['ends_at']->format('Y-m-d')) }}',
                    // Ao mexer no início, o fim acompanha mantendo a quinzena — mas
                    // continua editável pra sprint fora do padrão (feriado, campanha).
                    aoMudarInicio() {
                        if (!this.inicio) return;
                        const d = new Date(this.inicio + 'T00:00:00');
                        d.setDate(d.getDate() + {{ \App\Models\Sprint::DURATION_DAYS - 1 }});
                        this.fim = d.toISOString().slice(0, 10);
                    },
                    get nome() {
                        if (!this.inicio || !this.fim) return '—';
                        const f = (s) => { const d = new Date(s + 'T00:00:00');
                            return String(d.getDate()).padStart(2,'0') + '/' +
                                   String(d.getMonth()+1).padStart(2,'0') + '/' +
                                   String(d.getFullYear()).slice(-2); };
                        return `Sprint {{ $nextNumber }} (${f(this.inicio)} - ${f(this.fim)})`;
                    },
                  }">
                @csrf

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">
                            Início <span style="color:var(--orange)">*</span>
                        </label>
                        <input type="date" name="starts_at" required x-model="inicio" @change="aoMudarInicio()"
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        @error('starts_at') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">
                            Fim <span style="color:var(--orange)">*</span>
                        </label>
                        <input type="date" name="ends_at" required x-model="fim"
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        @error('ends_at') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="px-4 py-3" style="background:var(--s3); border:1px solid var(--border); border-radius:8px">
                    <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Vai se chamar</p>
                    <p class="text-sm font-semibold" style="color:var(--purple)" x-text="nome"></p>
                    <p class="text-xs mt-1.5" style="color:var(--muted)">
                        Quinzena de {{ \App\Models\Sprint::DURATION_DAYS }} dias, emendando na última sprint do calendário.
                        As datas continuam editáveis se precisar fugir do padrão.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Status</label>
                    <select name="status"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        @foreach(\App\Models\Sprint::$statuses as $key => $s)
                            <option value="{{ $key }}" {{ old('status', 'planning') === $key ? 'selected' : '' }}>
                                {{ $s['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                        class="px-5 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                        style="background:var(--purple)">
                        Criar Sprint
                    </button>
                    <a href="{{ route('sprints.index') }}" class="text-xs font-mono transition-colors" style="color:var(--muted)">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

</x-app-layout>

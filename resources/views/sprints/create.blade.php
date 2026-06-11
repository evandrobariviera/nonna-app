<x-app-layout>
    <x-slot name="header">Nova Sprint</x-slot>

    <div class="max-w-lg">
        <div class="card px-6 py-6">
            <form method="POST" action="{{ route('sprints.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">
                        Título <span style="color:var(--orange)">*</span>
                    </label>
                    <input type="text" name="title" required
                        value="{{ old('title') }}"
                        placeholder="Ex: Sprint 24 — 09-13 Jun/2026"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                    @error('title')
                        <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">
                            Início <span style="color:var(--orange)">*</span>
                        </label>
                        <input type="date" name="starts_at" required value="{{ old('starts_at') }}"
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">
                            Fim <span style="color:var(--orange)">*</span>
                        </label>
                        <input type="date" name="ends_at" required value="{{ old('ends_at') }}"
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Status</label>
                    <select name="status"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
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

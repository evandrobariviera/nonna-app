<x-superadmin-layout>

    <div class="mb-8">
        <a href="{{ route('superadmin.organizations.index') }}"
           class="text-xs font-semibold mb-3 inline-flex items-center gap-1"
           style="color: var(--muted)">
            ← Organizações
        </a>
        <h1 class="text-2xl font-black" style="color: var(--text)">{{ $organization->name }}</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">{{ $organization->slug }}</p>
    </div>

    <form method="POST" action="{{ route('superadmin.organizations.update', $organization) }}"
          class="max-w-xl space-y-6">
        @csrf
        @method('PUT')

        <div class="card p-6 space-y-4">
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">
                    Nome da organização
                </label>
                <input type="text" name="name" value="{{ old('name', $organization->name) }}" required
                       class="w-full rounded-lg border px-3 py-2 text-sm"
                       style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                @error('name') <p class="mt-1 text-xs" style="color:var(--red)">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">Plano</label>
                    <select name="plan" required
                            class="w-full rounded-lg border px-3 py-2 text-sm"
                            style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                        @foreach(\App\Models\Organization::$plans as $key => $label)
                            <option value="{{ $key }}" {{ old('plan', $organization->plan) === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('plan') <p class="mt-1 text-xs" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">Status</label>
                    <select name="status" required
                            class="w-full rounded-lg border px-3 py-2 text-sm"
                            style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                        @foreach(\App\Models\Organization::$statuses as $key => $label)
                            <option value="{{ $key }}" {{ old('status', $organization->status) === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('status') <p class="mt-1 text-xs" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Info só leitura --}}
            <div class="pt-4 border-t space-y-2" style="border-color: var(--border2)">
                <p class="text-xs" style="color: var(--muted)">
                    <span class="font-semibold">Owner:</span> {{ $organization->owner?->name }} ({{ $organization->owner?->email }})
                </p>
                <p class="text-xs" style="color: var(--muted)">
                    <span class="font-semibold">Criada em:</span> {{ $organization->created_at->format('d/m/Y H:i') }}
                </p>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('superadmin.organizations.index') }}"
               class="px-4 py-2 text-sm font-semibold rounded-lg"
               style="color: var(--muted); background: var(--s3)">
                Cancelar
            </a>
            <button type="submit" class="btn-primary px-5 py-2 text-sm rounded-lg font-semibold">
                Salvar Alterações
            </button>
        </div>
    </form>

</x-superadmin-layout>

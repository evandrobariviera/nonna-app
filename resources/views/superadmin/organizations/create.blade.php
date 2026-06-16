<x-superadmin-layout>

    <div class="mb-8">
        <a href="{{ route('superadmin.organizations.index') }}"
           class="text-xs font-semibold mb-3 inline-flex items-center gap-1"
           style="color: var(--muted)">
            ← Organizações
        </a>
        <h1 class="text-2xl font-black" style="color: var(--text)">Nova Organização</h1>
    </div>

    <form method="POST" action="{{ route('superadmin.organizations.store') }}"
          class="max-w-2xl space-y-6">
        @csrf

        {{-- Dados da organização --}}
        <div class="card p-6 space-y-4">
            <h2 class="text-sm font-bold" style="color: var(--text)">Dados da Agência</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">
                        Nome da organização
                    </label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full rounded-lg border px-3 py-2 text-sm"
                           style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                    @error('name') <p class="mt-1 text-xs" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">
                        Slug <span class="font-normal">(ex: nonna-digital)</span>
                    </label>
                    <input type="text" name="slug" value="{{ old('slug') }}" required
                           pattern="[a-z0-9\-]+" placeholder="apenas-letras-e-hifens"
                           class="w-full rounded-lg border px-3 py-2 text-sm font-mono"
                           style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                    @error('slug') <p class="mt-1 text-xs" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">Plano</label>
                <select name="plan" required
                        class="w-full rounded-lg border px-3 py-2 text-sm"
                        style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                    @foreach(\App\Models\Organization::$plans as $key => $label)
                        <option value="{{ $key }}" {{ old('plan', 'trial') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('plan') <p class="mt-1 text-xs" style="color:var(--red)">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Usuário responsável --}}
        <div class="card p-6 space-y-4">
            <h2 class="text-sm font-bold" style="color: var(--text)">Responsável (Owner)</h2>
            <p class="text-xs" style="color: var(--muted)">Será criado um novo usuário com acesso de owner à organização.</p>

            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">Nome</label>
                <input type="text" name="owner_name" value="{{ old('owner_name') }}" required
                       class="w-full rounded-lg border px-3 py-2 text-sm"
                       style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                @error('owner_name') <p class="mt-1 text-xs" style="color:var(--red)">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">E-mail</label>
                    <input type="email" name="owner_email" value="{{ old('owner_email') }}" required
                           class="w-full rounded-lg border px-3 py-2 text-sm"
                           style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                    @error('owner_email') <p class="mt-1 text-xs" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">Senha inicial</label>
                    <input type="text" name="owner_password" value="{{ old('owner_password') }}" required
                           placeholder="Mínimo 8 caracteres"
                           class="w-full rounded-lg border px-3 py-2 text-sm font-mono"
                           style="background: var(--s2); border-color: var(--border2); color: var(--text)">
                    @error('owner_password') <p class="mt-1 text-xs" style="color:var(--red)">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('superadmin.organizations.index') }}"
               class="px-4 py-2 text-sm font-semibold rounded-lg"
               style="color: var(--muted); background: var(--s3)">
                Cancelar
            </a>
            <button type="submit" class="btn-primary px-5 py-2 text-sm rounded-lg font-semibold">
                Criar Organização
            </button>
        </div>
    </form>

</x-superadmin-layout>

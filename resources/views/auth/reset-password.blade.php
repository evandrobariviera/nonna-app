<x-guest-layout>
    <x-slot name="title">Nova senha — {{ config('app.name', 'Nonna OS') }}</x-slot>

    <div class="mb-8">
        <h2 class="text-2xl font-black mb-1" style="color: var(--text)">Criar nova senha</h2>
        <p class="text-sm" style="color: var(--muted)">Escolha uma senha segura para sua conta.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">
                E-mail
            </label>
            <input id="email" type="email" name="email"
                   value="{{ old('email', $request->email) }}"
                   required autofocus autocomplete="username"
                   class="w-full rounded-lg border px-3 py-2.5 text-sm outline-none focus:ring-2"
                   style="background: var(--s1); border-color: var(--border2); color: var(--text);
                          --tw-ring-color: var(--purple); --tw-ring-opacity: 0.25">
            @error('email')
                <p class="mt-1.5 text-xs" style="color: var(--red)">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">
                Nova senha
            </label>
            <input id="password" type="password" name="password"
                   required autocomplete="new-password" placeholder="Mínimo 8 caracteres"
                   class="w-full rounded-lg border px-3 py-2.5 text-sm outline-none focus:ring-2"
                   style="background: var(--s1); border-color: var(--border2); color: var(--text);
                          --tw-ring-color: var(--purple); --tw-ring-opacity: 0.25">
            @error('password')
                <p class="mt-1.5 text-xs" style="color: var(--red)">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">
                Confirmar nova senha
            </label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                   required autocomplete="new-password" placeholder="••••••••"
                   class="w-full rounded-lg border px-3 py-2.5 text-sm outline-none focus:ring-2"
                   style="background: var(--s1); border-color: var(--border2); color: var(--text);
                          --tw-ring-color: var(--purple); --tw-ring-opacity: 0.25">
            @error('password_confirmation')
                <p class="mt-1.5 text-xs" style="color: var(--red)">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
                class="w-full py-2.5 rounded-lg text-sm font-bold text-white transition-opacity hover:opacity-90"
                style="background: var(--grad)">
            Redefinir senha
        </button>
    </form>
</x-guest-layout>

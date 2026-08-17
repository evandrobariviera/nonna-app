<x-portal-guest-layout>
    <div class="mb-8">
        <span class="portal-badge mb-3" style="display:inline-flex">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Portal do Cliente
        </span>
        <h2 class="text-2xl font-black mt-3 mb-1" style="color: var(--text)">Acesse sua conta</h2>
        <p class="text-sm" style="color: var(--muted)">Entre com suas credenciais para acompanhar seus projetos</p>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-lg px-4 py-3 text-sm font-semibold"
             style="background: rgba(5,150,105,.08); color: var(--green); border: 1px solid rgba(5,150,205,.2)">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('portal.login.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">
                E-mail
            </label>
            <input id="email" type="email" name="email"
                   value="{{ old('email') }}"
                   required autofocus autocomplete="username"
                   placeholder="seu@email.com"
                   class="w-full rounded-lg border px-3 py-2.5 text-sm transition-colors outline-none
                          focus:ring-2"
                   style="background: var(--s1); border-color: var(--border2); color: var(--text);
                          --tw-ring-color: var(--purple); --tw-ring-opacity: 0.25">
            @error('email')
                <p class="mt-1.5 text-xs" style="color: var(--red)">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">
                Senha
            </label>
            <input id="password" type="password" name="password"
                   required autocomplete="current-password"
                   placeholder="••••••••"
                   class="w-full rounded-lg border px-3 py-2.5 text-sm transition-colors outline-none
                          focus:ring-2"
                   style="background: var(--s1); border-color: var(--border2); color: var(--text);
                          --tw-ring-color: var(--purple); --tw-ring-opacity: 0.25">
            @error('password')
                <p class="mt-1.5 text-xs" style="color: var(--red)">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-2">
            <input id="remember_me" type="checkbox" name="remember"
                   class="rounded border w-4 h-4 cursor-pointer"
                   style="accent-color: var(--purple); border-color: var(--border2)">
            <label for="remember_me" class="text-sm cursor-pointer" style="color: var(--muted)">
                Manter conectado
            </label>
        </div>

        <button type="submit"
                class="w-full py-2.5 rounded-lg text-sm font-bold text-white transition-opacity hover:opacity-90"
                style="background: var(--grad)">
            Entrar
        </button>
    </form>
</x-portal-guest-layout>

<x-guest-layout>
    <div class="mb-8">
        <h2 class="text-2xl font-black mb-1" style="color: var(--text)">Bem-vindo de volta</h2>
        <p class="text-sm" style="color: var(--muted)">Entre com suas credenciais para continuar</p>
    </div>

    {{-- Status de sessão (ex: "link de redefinição enviado") --}}
    @if (session('status'))
        <div class="mb-5 rounded-lg px-4 py-3 text-sm font-semibold"
             style="background: rgba(5,150,105,.08); color: var(--green); border: 1px solid rgba(5,150,105,.2)">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        {{-- E-mail --}}
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

        {{-- Senha --}}
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="text-xs font-semibold" style="color: var(--muted)">
                    Senha
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                       class="text-xs font-semibold transition-colors"
                       style="color: var(--purple)"
                       onmouseover="this.style.opacity='.75'"
                       onmouseout="this.style.opacity='1'">
                        Esqueceu a senha?
                    </a>
                @endif
            </div>
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

        {{-- Lembrar --}}
        <div class="flex items-center gap-2">
            <input id="remember_me" type="checkbox" name="remember"
                   class="rounded border w-4 h-4 cursor-pointer"
                   style="accent-color: var(--purple); border-color: var(--border2)">
            <label for="remember_me" class="text-sm cursor-pointer" style="color: var(--muted)">
                Manter conectado
            </label>
        </div>

        {{-- Botão --}}
        <button type="submit"
                class="w-full py-2.5 rounded-lg text-sm font-bold text-white transition-opacity hover:opacity-90"
                style="background: var(--grad)">
            Entrar
        </button>
    </form>
</x-guest-layout>

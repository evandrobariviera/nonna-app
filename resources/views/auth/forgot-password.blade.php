<x-guest-layout>
    <x-slot name="title">Recuperar senha — {{ config('app.name', 'Nonna OS') }}</x-slot>

    <div class="mb-8">
        <h2 class="text-2xl font-black mb-1" style="color: var(--text)">Recuperar senha</h2>
        <p class="text-sm" style="color: var(--muted)">
            Informe seu e-mail e enviaremos um link para redefinir sua senha.
        </p>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-lg px-4 py-3 text-sm font-semibold"
             style="background: rgba(5,150,105,.08); color: var(--green); border: 1px solid rgba(5,150,105,.2)">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-xs font-semibold mb-1.5" style="color: var(--muted)">
                E-mail
            </label>
            <input id="email" type="email" name="email"
                   value="{{ old('email') }}"
                   required autofocus placeholder="seu@email.com"
                   class="w-full rounded-lg border px-3 py-2.5 text-sm outline-none focus:ring-2"
                   style="background: var(--s1); border-color: var(--border2); color: var(--text);
                          --tw-ring-color: var(--purple); --tw-ring-opacity: 0.25">
            @error('email')
                <p class="mt-1.5 text-xs" style="color: var(--red)">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
                class="w-full py-2.5 rounded-lg text-sm font-bold text-white transition-opacity hover:opacity-90"
                style="background: var(--grad)">
            Enviar link de recuperação
        </button>

        <div class="text-center">
            <a href="{{ route('login') }}" class="text-xs font-semibold" style="color: var(--muted)"
               onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                ← Voltar para o login
            </a>
        </div>
    </form>
</x-guest-layout>

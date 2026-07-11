@php
    $inputStyle = "background:var(--s1); border:1px solid var(--border2); color:var(--text); outline:none; font-family:'Syne',sans-serif";
@endphp

<div class="card">
    <div class="card-header">
        <p class="stat-label" style="margin-bottom:2px">Perfil</p>
        <h3 class="text-sm font-bold" style="color:var(--text)">Informações do Perfil</h3>
        <p class="text-xs mt-1" style="color:var(--muted)">Atualize seu nome e endereço de e-mail.</p>
    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="p-6 space-y-5">
        @csrf
        @method('patch')

        <div>
            <label class="stat-label block mb-2">Nome</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name"
                   class="w-full px-3 py-2 text-sm" style="{{ $inputStyle }}">
            @error('name') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="stat-label block mb-2">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username"
                   class="w-full px-3 py-2 text-sm" style="{{ $inputStyle }}">
            @error('email') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-2">
                    <p class="text-xs" style="color:var(--muted)">
                        Seu e-mail ainda não foi verificado.
                        <button form="send-verification" class="underline font-semibold" style="color:var(--purple)">
                            Clique aqui para reenviar o e-mail de verificação.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="text-xs mt-2 font-semibold" style="color:var(--green)">
                            Um novo link de verificação foi enviado para seu e-mail.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="btn btn-primary text-sm">Salvar</button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-xs" style="color:var(--green)"
                >Salvo.</p>
            @endif
        </div>
    </form>
</div>

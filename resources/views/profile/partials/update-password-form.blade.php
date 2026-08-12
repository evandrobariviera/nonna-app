@php
    $inputStyle = "background:var(--s1); border:1px solid var(--border2); color:var(--text); outline:none; font-family:'Inter',sans-serif";
@endphp

<div class="card">
    <div class="card-header">
        <p class="stat-label" style="margin-bottom:2px">Perfil</p>
        <h3 class="text-sm font-bold" style="color:var(--text)">Alterar Senha</h3>
        <p class="text-xs mt-1" style="color:var(--muted)">Use uma senha longa e aleatória para manter sua conta segura.</p>
    </div>

    <form method="post" action="{{ route('password.update') }}" class="p-6 space-y-5">
        @csrf
        @method('put')

        <div>
            <label class="stat-label block mb-2">Senha Atual</label>
            <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password"
                   class="w-full px-3 py-2 text-sm" style="{{ $inputStyle }}">
            @error('current_password', 'updatePassword') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="stat-label block mb-2">Nova Senha</label>
            <input id="update_password_password" name="password" type="password" autocomplete="new-password"
                   class="w-full px-3 py-2 text-sm" style="{{ $inputStyle }}">
            @error('password', 'updatePassword') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="stat-label block mb-2">Confirmar Nova Senha</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                   class="w-full px-3 py-2 text-sm" style="{{ $inputStyle }}">
            @error('password_confirmation', 'updatePassword') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="btn btn-primary text-sm">Salvar</button>

            @if (session('status') === 'password-updated')
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

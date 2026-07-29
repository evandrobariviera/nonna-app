<x-app-layout>
    <x-slot name="header">Perfil</x-slot>

    <div class="max-w-2xl mx-auto flex flex-col gap-6">

        @if(session('status') === 'avatar-updated')
            <div class="px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
                Foto de perfil atualizada.
            </div>
        @elseif(session('status') === 'avatar-removed')
            <div class="px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
                Foto de perfil removida.
            </div>
        @endif

        {{-- Foto de Perfil --}}
        <div class="card">
            <div class="card-header">
                <p class="stat-label" style="margin-bottom:2px">Perfil</p>
                <h3 class="text-sm font-bold" style="color:var(--text)">Foto de Perfil</h3>
            </div>
            <div class="p-6 flex items-center gap-5">
                <x-user-avatar :user="$user" size="20" />
                <div class="flex-1">
                    <form method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data" class="flex items-center gap-3 flex-wrap">
                        @csrf
                        <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" required
                               class="text-sm" style="color:var(--muted2)">
                        <button type="submit" class="btn btn-primary text-sm">Enviar foto</button>
                    </form>
                    @error('avatar') <p class="text-xs mt-2" style="color:var(--red)">{{ $message }}</p> @enderror
                    <p class="text-xs mt-2" style="color:var(--muted)">JPG, PNG ou WEBP, até 3 MB.</p>
                    @if($user->avatar_path)
                        <form method="POST" action="{{ route('profile.avatar.destroy') }}" class="mt-2"
                              onsubmit="return confirm('Remover sua foto de perfil?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-xs">
                                Remover foto
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        @include('profile.partials.update-profile-information-form')

        @include('profile.partials.update-password-form')

        @include('profile.partials.delete-user-form')

    </div>
</x-app-layout>

<div class="card" x-data="{ confirmOpen: false }">
    <div class="card-header">
        <p class="stat-label" style="margin-bottom:2px">Perfil</p>
        <h3 class="text-sm font-bold" style="color:var(--text)">Excluir Conta</h3>
        <p class="text-xs mt-1" style="color:var(--muted)">
            Uma vez excluída, todos os seus dados serão permanentemente removidos. Baixe qualquer informação que queira manter antes de excluir sua conta.
        </p>
    </div>

    <div class="p-6">
        <button type="button" @click="confirmOpen = true" class="btn btn-danger text-sm">Excluir Conta</button>
    </div>

    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="absolute inset-0 bg-black/60" @click="confirmOpen = false"></div>
        <div class="relative w-full max-w-md rounded-xl shadow-2xl p-6"
             style="background:var(--s1); border:1px solid var(--border)"
             x-transition:enter="transition duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            <h3 class="text-base font-bold mb-2" style="color:var(--text)">Tem certeza que deseja excluir sua conta?</h3>
            <p class="text-xs mb-5" style="color:var(--muted)">
                Uma vez excluída, todos os seus dados serão permanentemente removidos. Digite sua senha para confirmar.
            </p>

            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">Senha</label>
                <input id="password" name="password" type="password" placeholder="Senha"
                       class="w-full rounded-lg border px-3 py-2 text-sm"
                       style="background:var(--s2); border-color:var(--border2); color:var(--text)">
                @error('password', 'userDeletion') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror

                <div class="flex justify-end gap-3 pt-5">
                    <button type="button" @click="confirmOpen = false"
                            class="px-4 py-2 text-sm font-semibold rounded-lg"
                            style="color:var(--muted); background:var(--s3)">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-danger text-sm">Excluir Conta</button>
                </div>
            </form>
        </div>
    </div>
</div>

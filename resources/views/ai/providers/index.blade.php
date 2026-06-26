<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Providers de IA — Chaves de API</span>
    </x-slot>

    <div style="max-width:900px">

        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded text-sm font-medium"
                 style="background:rgba(52,211,153,.15); color:#34d399; border:1px solid rgba(52,211,153,.25)">
                {{ session('success') }}
            </div>
        @endif

        <p class="text-sm mb-6" style="color:var(--muted)">
            Cadastre aqui as chaves de API de cada provedor. As chaves ficam encriptadas no banco e nunca são expostas em texto puro.
        </p>

        {{-- Grid de providers --}}
        <div class="grid gap-5">
            @foreach($providers as $provider)
            <div style="border:1px solid var(--border2); border-radius:8px; overflow:hidden">

                {{-- Header do provider --}}
                <div class="flex items-center justify-between px-5 py-4"
                     style="background:var(--s2); border-bottom:1px solid var(--border2)">
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded flex items-center justify-center text-xs font-black"
                             style="background:var(--purple); color:#fff">
                            {{ strtoupper(substr($provider->slug, 0, 2)) }}
                        </div>
                        <div>
                            <div class="font-bold text-sm" style="color:var(--text)">{{ $provider->name }}</div>
                            <div class="text-xs font-mono" style="color:var(--muted)">{{ $provider->base_url }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2 py-1 rounded font-mono"
                              style="background:var(--s3); color:var(--muted)">
                            {{ count($provider->models ?? []) }} modelos
                        </span>
                        <span class="text-xs px-2 py-1 rounded font-semibold"
                              style="background:{{ $provider->apiKeys->where('is_active', true)->count() > 0 ? 'rgba(52,211,153,.15)' : 'var(--s3)' }};
                                     color:{{ $provider->apiKeys->where('is_active', true)->count() > 0 ? '#34d399' : 'var(--muted)' }}">
                            {{ $provider->apiKeys->where('is_active', true)->count() }} ativa(s)
                        </span>
                    </div>
                </div>

                <div class="px-5 py-4">

                    {{-- Chaves existentes --}}
                    @if($provider->apiKeys->count() > 0)
                    <div class="mb-4 grid gap-2">
                        @foreach($provider->apiKeys as $key)
                        <div class="flex items-center justify-between py-2 px-3 rounded"
                             style="background:var(--s3); border:1px solid var(--border2)">
                            <div class="flex items-center gap-3">
                                <span class="h-2 w-2 rounded-full flex-shrink-0"
                                      style="background:{{ $key->is_active ? '#34d399' : 'var(--muted2)' }}"></span>
                                <div>
                                    <span class="text-xs font-semibold" style="color:var(--text)">{{ $key->label }}</span>
                                    <span class="text-xs font-mono ml-2" style="color:var(--muted)">{{ $key->maskedKey() }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST"
                                      action="{{ route('ai.providers.keys.toggle', [$provider, $key]) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                            class="text-xs px-2 py-1 rounded transition-colors"
                                            style="color:var(--muted); border:1px solid var(--border2); background:none; cursor:pointer">
                                        {{ $key->is_active ? 'Desativar' : 'Ativar' }}
                                    </button>
                                </form>
                                <form method="POST"
                                      action="{{ route('ai.providers.keys.destroy', [$provider, $key]) }}"
                                      onsubmit="return confirm('Remover esta chave?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="text-xs px-2 py-1 rounded transition-colors"
                                            style="color:var(--red); border:1px solid rgba(239,68,68,.25); background:none; cursor:pointer">
                                        Remover
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                        <p class="text-xs mb-4" style="color:var(--muted)">Nenhuma chave cadastrada ainda.</p>
                    @endif

                    {{-- Formulário para nova chave --}}
                    <div x-data="{ open: false }">
                        <button type="button" @click="open = !open"
                                class="text-xs font-semibold flex items-center gap-1 transition-colors"
                                style="color:var(--purple); background:none; border:none; cursor:pointer; padding:0">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Adicionar chave
                        </button>

                        <div x-show="open" x-transition style="display:none" class="mt-3">
                            <form method="POST"
                                  action="{{ route('ai.providers.keys.store', $provider) }}"
                                  class="flex items-end gap-3">
                                @csrf
                                <div class="flex-1">
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--muted); font-family:'IBM Plex Mono',monospace; letter-spacing:.05em">RÓTULO</label>
                                    <input type="text" name="label" placeholder="ex: Chave Principal"
                                           required
                                           class="w-full rounded px-3 py-2 text-sm"
                                           style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                                </div>
                                <div style="flex:2">
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--muted); font-family:'IBM Plex Mono',monospace; letter-spacing:.05em">CHAVE DE API</label>
                                    <input type="password" name="api_key" placeholder="sk-..."
                                           required autocomplete="off"
                                           class="w-full rounded px-3 py-2 text-sm font-mono"
                                           style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                                </div>
                                <button type="submit"
                                        class="px-4 py-2 rounded text-xs font-bold transition-colors flex-shrink-0"
                                        style="background:var(--purple); color:#fff; border:none; cursor:pointer">
                                    Salvar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Modelos disponíveis --}}
                @if($provider->models)
                <div class="px-5 pb-4">
                    <div class="text-xs font-semibold mb-2" style="color:var(--muted); font-family:'IBM Plex Mono',monospace; letter-spacing:.05em">MODELOS</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($provider->models as $model)
                        <div class="flex items-center gap-2 px-2 py-1 rounded text-xs"
                             style="background:var(--s3); border:1px solid var(--border2)">
                            <span class="font-mono" style="color:var(--text)">{{ $model['id'] }}</span>
                            <span style="color:var(--muted)">·</span>
                            <span style="color:var(--muted)">in ${{ number_format($model['input_per_1k'], 5) }}</span>
                            <span style="color:var(--muted)">/</span>
                            <span style="color:var(--muted)">out ${{ number_format($model['output_per_1k'], 5) }}</span>
                            <span style="color:var(--muted2); font-size:9px">por 1K tokens</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>
            @endforeach
        </div>
    </div>
</x-app-layout>

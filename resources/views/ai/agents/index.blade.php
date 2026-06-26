<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Agentes de IA</span>
    </x-slot>

    <div style="max-width:960px">

        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded text-sm font-medium"
                 style="background:rgba(52,211,153,.15); color:#34d399; border:1px solid rgba(52,211,153,.25)">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between mb-6">
            <p class="text-sm" style="color:var(--muted)">
                Agentes são especialistas configuráveis — cada um com seu prompt, modelo e comportamento.
            </p>
            <a href="{{ route('ai.agents.create') }}"
               class="px-4 py-2 rounded text-xs font-bold"
               style="background:var(--purple); color:#fff">
                + Novo Agente
            </a>
        </div>

        @if($agents->isEmpty())
            <div class="text-center py-16" style="border:1px dashed var(--border2); border-radius:8px">
                <div class="text-4xl mb-3">🤖</div>
                <div class="text-sm font-semibold mb-1" style="color:var(--text)">Nenhum agente criado ainda</div>
                <div class="text-xs mb-4" style="color:var(--muted)">Crie o primeiro agente para começar a usar IA no sistema.</div>
                <a href="{{ route('ai.agents.create') }}"
                   class="px-4 py-2 rounded text-xs font-bold"
                   style="background:var(--purple); color:#fff">
                    Criar primeiro agente
                </a>
            </div>
        @else
            <div class="grid gap-3">
                @foreach($agents as $agent)
                <div style="border:1px solid var(--border2); border-radius:8px; overflow:hidden"
                     class="{{ $agent->is_active ? '' : 'opacity-60' }}">
                    <div class="flex items-start justify-between px-5 py-4">
                        <div class="flex items-start gap-3">
                            <div class="h-9 w-9 rounded flex items-center justify-center text-xs font-black flex-shrink-0"
                                 style="background:{{ $agent->is_active ? 'var(--purple)' : 'var(--s3)' }}; color:{{ $agent->is_active ? '#fff' : 'var(--muted)' }}">
                                {{ strtoupper(substr($agent->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-sm" style="color:var(--text)">{{ $agent->name }}</span>
                                    @if(!$agent->is_active)
                                        <span class="text-xs px-2 py-px rounded"
                                              style="background:var(--s3); color:var(--muted)">inativo</span>
                                    @endif
                                </div>
                                @if($agent->description)
                                    <div class="text-xs mt-0.5" style="color:var(--muted)">{{ $agent->description }}</div>
                                @endif
                                <div class="flex items-center gap-3 mt-2 flex-wrap">
                                    <span class="text-xs font-mono px-2 py-px rounded"
                                          style="background:var(--s2); color:var(--purple); border:1px solid rgba(106,90,205,.2)">
                                        {{ $agent->model }}
                                    </span>
                                    <span class="text-xs" style="color:var(--muted)">
                                        {{ $agent->provider->name ?? '—' }}
                                    </span>
                                    <span class="text-xs" style="color:var(--muted)">
                                        temp {{ $agent->temperature }}
                                    </span>
                                    <span class="text-xs" style="color:var(--muted)">
                                        max {{ number_format($agent->max_tokens) }} tokens
                                    </span>
                                    <span class="text-xs px-2 py-px rounded"
                                          style="background:var(--s3); color:var(--muted2)">
                                        {{ $agent->context_scope === 'client' ? 'contexto cliente' : 'global' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0 ml-4">
                            <a href="{{ route('ai.agents.show', $agent) }}"
                               class="text-xs px-3 py-1.5 rounded font-semibold"
                               style="color:var(--purple); border:1px solid rgba(106,90,205,.3); background:rgba(106,90,205,.07)">
                                Testar
                            </a>
                            <form method="POST" action="{{ route('ai.agents.toggle', $agent) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="text-xs px-3 py-1.5 rounded transition-colors"
                                        style="color:var(--muted); border:1px solid var(--border2); background:none; cursor:pointer">
                                    {{ $agent->is_active ? 'Desativar' : 'Ativar' }}
                                </button>
                            </form>
                            <a href="{{ route('ai.agents.edit', $agent) }}"
                               class="text-xs px-3 py-1.5 rounded transition-colors"
                               style="color:var(--text); border:1px solid var(--border2)">
                                Editar
                            </a>
                            <form method="POST" action="{{ route('ai.agents.destroy', $agent) }}"
                                  onsubmit="return confirm('Remover este agente?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="text-xs px-3 py-1.5 rounded"
                                        style="color:var(--red); border:1px solid rgba(239,68,68,.25); background:none; cursor:pointer">
                                    Remover
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Preview do system prompt --}}
                    <div class="px-5 pb-4">
                        <div class="text-xs p-3 rounded font-mono"
                             style="background:var(--s3); color:var(--muted); border:1px solid var(--border2); white-space:pre-wrap; max-height:60px; overflow:hidden; line-height:1.5">{{ Str::limit($agent->system_prompt, 200) }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>

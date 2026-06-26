<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Automações</span>
    </x-slot>

    <div style="max-width:960px">

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold"
                 style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between mb-6">
            <p class="text-sm" style="color:var(--muted)">
                Regras que disparam ações automaticamente — gatilhos operacionais e de IA.
            </p>
            <a href="{{ route('automations.create') }}"
               class="px-4 py-2 text-xs font-bold"
               style="background:var(--purple); color:#fff">
                + Nova Automação
            </a>
        </div>

        @if($automations->isEmpty())
            <div class="text-center py-16" style="border:1px dashed var(--border2)">
                <div class="text-4xl mb-3">⚡</div>
                <div class="text-sm font-semibold mb-1" style="color:var(--text)">Nenhuma automação criada ainda</div>
                <div class="text-xs mb-4" style="color:var(--muted)">Crie regras do tipo "Se → Então" para automatizar o sistema.</div>
                <a href="{{ route('automations.create') }}"
                   class="px-4 py-2 text-xs font-bold"
                   style="background:var(--purple); color:#fff">
                    Criar primeira automação
                </a>
            </div>
        @else
            @foreach($automations as $entityType => $group)
                <div class="text-xs font-semibold uppercase tracking-widest mb-3 mt-6"
                     style="color:var(--muted); letter-spacing:.1em">
                    {{ \App\Models\Automation::$entityTypes[$entityType] ?? $entityType }}
                </div>

                <div class="flex flex-col gap-2 mb-2">
                    @foreach($group as $automation)
                        <div class="card" style="padding:1.25rem 1.5rem">
                            <div class="flex items-start gap-4">

                                {{-- Toggle ativo --}}
                                <form action="{{ route('automations.toggle', $automation) }}" method="POST" style="padding-top:2px; flex-shrink:0">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                        title="{{ $automation->is_active ? 'Pausar' : 'Ativar' }}"
                                        style="width:36px; height:20px; border-radius:999px; border:none; cursor:pointer; position:relative; transition:background .2s;
                                               background:{{ $automation->is_active ? 'var(--purple)' : 'var(--border2)' }}">
                                        <span style="position:absolute; top:2px; width:16px; height:16px; border-radius:50%; background:#fff;
                                                     transition:left .2s; left:{{ $automation->is_active ? '18px' : '2px' }}"></span>
                                    </button>
                                </form>

                                {{-- Info --}}
                                <div style="flex:1; min-width:0">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <span class="text-sm font-semibold" style="color:var(--text)">{{ $automation->name }}</span>
                                        @if(!$automation->is_active)
                                            <span class="badge" style="font-size:.7rem">Pausada</span>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-2 flex-wrap" style="font-size:.82rem">
                                        <span style="background:rgba(106,90,205,.1); color:var(--purple); padding:.2rem .6rem; border-radius:6px; border:1px solid rgba(106,90,205,.2)">
                                            Se: {{ $automation->triggerSummary() }}
                                        </span>
                                        <span style="color:var(--muted)">→</span>
                                        <span style="background:rgba(255,140,0,.1); color:var(--orange); padding:.2rem .6rem; border-radius:6px; border:1px solid rgba(255,140,0,.2)">
                                            Então: {{ $automation->actionSummary() }}
                                        </span>
                                    </div>

                                    @if($automation->description)
                                        <p class="text-xs mt-1" style="color:var(--muted)">{{ $automation->description }}</p>
                                    @endif
                                </div>

                                {{-- Ações --}}
                                <div class="flex items-center gap-1" style="flex-shrink:0">
                                    <a href="{{ route('automations.logs', $automation) }}"
                                       title="Ver logs"
                                       class="flex items-center px-2 py-1.5 text-xs transition-colors"
                                       style="color:var(--muted)"
                                       onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                                        Logs
                                    </a>
                                    <a href="{{ route('automations.edit', $automation) }}"
                                       class="flex items-center px-2 py-1.5 text-xs transition-colors"
                                       style="color:var(--muted)"
                                       onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                                        Editar
                                    </a>
                                    <form action="{{ route('automations.destroy', $automation) }}" method="POST"
                                          onsubmit="return confirm('Remover esta automação?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="px-2 py-1.5 text-xs transition-colors"
                                                style="color:var(--muted); background:none; border:none; cursor:pointer"
                                                onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
                                            Remover
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>
</x-app-layout>

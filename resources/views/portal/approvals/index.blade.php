<x-portal-layout>
    <x-slot name="title">Central de Aprovações</x-slot>

    {{-- Central → Projeto → Peça. Um card por projeto com algo que passou por
         aprovação, mais o grupo "Peças avulsas" (tarefas sem projeto). --}}
    <div class="mb-6">
        <h1 class="text-2xl font-black" style="color: var(--text)">Central de Aprovações</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">
            @if($totalPending > 0)
                {{ $totalPending }} {{ $totalPending === 1 ? 'peça aguardando' : 'peças aguardando' }} você
                em {{ $groupsWithPending }} {{ $groupsWithPending === 1 ? 'projeto' : 'projetos' }} · {{ $client->company_name }}
            @else
                Nada aguardando você no momento · {{ $client->company_name }}
            @endif
        </p>
    </div>

    <div x-data="{ tab: 'andamento' }">
        <div class="flex gap-2 mb-6" role="tablist" style="border-bottom: 1px solid var(--border2)">
            <button type="button" role="tab" @click="tab = 'andamento'"
                    class="px-4 py-3 text-sm font-bold"
                    :style="tab === 'andamento' ? 'color: var(--purple); box-shadow: inset 0 -3px 0 var(--purple)' : 'color: var(--muted)'">
                Em andamento ({{ $openGroups->count() }})
            </button>
            <button type="button" role="tab" @click="tab = 'concluidos'"
                    class="px-4 py-3 text-sm font-bold"
                    :style="tab === 'concluidos' ? 'color: var(--purple); box-shadow: inset 0 -3px 0 var(--purple)' : 'color: var(--muted)'">
                Concluídos ({{ $doneGroups->count() }})
            </button>
        </div>

        @foreach(['andamento' => $openGroups, 'concluidos' => $doneGroups] as $tabKey => $groups)
            <div x-show="tab === '{{ $tabKey }}'" @if($tabKey !== 'andamento') x-cloak @endif>
                @if($groups->isEmpty())
                    <div class="card p-8 text-center">
                        <p class="text-sm" style="color: var(--muted)">
                            {{ $tabKey === 'andamento' ? 'Nenhum projeto com aprovação em andamento.' : 'Nenhum projeto com tudo aprovado ainda.' }}
                        </p>
                    </div>
                @else
                    <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr))">
                        @foreach($groups as $g)
                            @php
                                $s = $g['summary'];
                                if ($s['pending'] > 0) {
                                    $badge = [$s['pending'] . ' aguardando você', 'rgba(100, 59, 142,.08)', 'var(--purple)'];
                                } elseif ($g['finished']) {
                                    $badge = ['Tudo aprovado', 'rgba(5,150,105,.1)', 'var(--green)'];
                                } else {
                                    $badge = ['Em ajuste', 'rgba(238, 121, 25,.08)', 'var(--orange)'];
                                }
                            @endphp
                            <a href="{{ $g['url'] }}" class="card flex flex-col overflow-hidden" style="text-decoration:none; padding:0">
                                <div class="flex items-center justify-center" style="height: 130px; background: var(--s3)">
                                    @if($g['thumb'])
                                        <img src="{{ $g['thumb']->url() }}" alt="" class="w-full h-full object-cover" loading="lazy">
                                    @else
                                        <x-icon name="{{ $g['project'] ? 'folder-kanban' : 'package' }}" size="30" style="color: var(--muted)" />
                                    @endif
                                </div>
                                <div class="p-4 flex flex-col gap-2.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--muted)">{{ $g['type_label'] }}</span>
                                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full flex-shrink-0" style="background: {{ $badge[1] }}; color: {{ $badge[2] }}">{{ $badge[0] }}</span>
                                    </div>
                                    <span class="text-base font-black" style="color: var(--text); line-height: 1.3">{{ $g['title'] }}</span>
                                    <div class="w-full h-1.5 rounded-full overflow-hidden" style="background: var(--s3)">
                                        <div class="h-1.5 rounded-full" style="width: {{ $s['percent'] }}%; background: var(--purple)"></div>
                                    </div>
                                    <span class="text-xs" style="color: var(--muted)">{{ $s['approved'] }} de {{ $s['total'] }} aprovados</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

</x-portal-layout>

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">Performance</p>
            <h1 class="text-2xl font-black text-[var(--text)]">Central de Campanhas</h1>
        </div>
    </x-slot>

    @php
        $filters = ['subir' => 'Para subir', 'criado' => 'Criado · pausado', 'ar' => 'No ar', 'todos' => 'Todas'];
    @endphp

    <div class="py-8 px-6">
        @include('campaign-central._tabs')

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex flex-wrap gap-1.5">
                @foreach($filters as $key => $label)
                    <a href="{{ route('ad-creations.index', array_filter(['situacao' => $key, 'client_id' => $clientId])) }}"
                       class="px-3 py-1 rounded-full text-sm border"
                       style="{{ $filter === $key ? 'background:color-mix(in srgb, var(--purple) 10%, transparent); border-color:color-mix(in srgb, var(--purple) 30%, transparent); color:var(--purple); font-weight:600' : 'border-color:var(--border); color:var(--muted2)' }}">
                        {{ $label }}{{ $key !== 'todos' ? ' · ' . ($counts[$key] ?? 0) : '' }}
                    </a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('ad-creations.index') }}">
                <input type="hidden" name="situacao" value="{{ $filter }}">
                <select name="client_id" onchange="this.form.submit()" class="filter-select" aria-label="Cliente">
                    <option value="todos">Todos os clientes</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->displayName() }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <p class="text-sm mb-4" style="color:var(--muted2)">Artes de tarefas com destino "Campanhas patrocinadas" aprovadas pelo cliente nos últimos 45 dias. A legenda aprovada vira o texto do anúncio, e o anúncio nasce <b>pausado</b>.</p>

        @if($searchCampaigns->isNotEmpty())
            <details class="card p-4 mb-5">
                <summary class="cursor-pointer text-sm font-semibold" style="color:var(--text)">
                    Google · anúncio de Pesquisa <span class="font-normal text-xs" style="color:var(--muted)">{{ $searchCampaigns->count() }} campanha(s) ativa(s) — a IA sugere os textos, o anúncio nasce pausado</span>
                </summary>
                <div class="flex flex-col gap-1.5 mt-3">
                    @foreach($searchCampaigns as $sc)
                        <div class="flex items-center justify-between gap-2 flex-wrap rounded px-3 py-2" style="background:var(--s2)">
                            <span class="text-sm min-w-0"><b style="color:var(--text)">{{ $sc->adAccount?->client?->displayName() }}</b> <span class="text-xs break-all" style="color:var(--muted2)">{{ $sc->name }}</span></span>
                            <a href="{{ route('google-search-ads.create', $sc) }}" class="btn btn-sm"><x-icon name="search" size="13" />Novo anúncio</a>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill, minmax(230px, 1fr))">
            @forelse($tasks as $task)
                @php
                    $image = $images->get($task->id)?->first();
                    $taskCreations = $creations->get($task->id, collect());
                    $round = $task->latestApprovalRound;
                @endphp
                <article class="card overflow-hidden flex flex-col">
                    <a href="{{ route('tasks.show', $task) }}" class="block aspect-square" style="background:var(--s2)"
                       @click="if (!$event.ctrlKey && !$event.metaKey) { $event.preventDefault(); $store.taskPopup.open('{{ route('tasks.show', $task) }}') }">
                        @if($image && \App\Services\AdSync\MetaAdCreator::isVideo($image))
                            <video src="{{ $image->url() }}#t=0.5" preload="metadata" muted playsinline class="w-full h-full object-cover"></video>
                        @elseif($image)
                            <img src="{{ $image->url() }}" alt="" loading="lazy" class="w-full h-full object-cover">
                        @else
                            <span class="w-full h-full flex items-center justify-center text-xs" style="color:var(--muted)">sem imagem entregue</span>
                        @endif
                    </a>
                    <div class="p-3 flex flex-col gap-1 flex-1">
                        <p class="font-semibold text-sm leading-snug" style="color:var(--text)">{{ $task->title }}</p>
                        <p class="text-xs" style="color:var(--muted)">{{ $task->client?->displayName() }} · aprovada em {{ ($round?->resolved_at ?? $round?->updated_at)?->format('d/m') }}</p>
                        @if($round?->caption)
                            <p class="text-xs line-clamp-2" style="color:var(--muted2)">"{{ $round->caption }}"</p>
                        @endif
                        <div class="mt-auto pt-2 flex items-center justify-between gap-2 flex-wrap">
                            @if($taskCreations->isNotEmpty())
                                @php $c = $taskCreations->first(); @endphp
                                <span class="badge badge-{{ $c->statusColor() }}">{{ $c->statusLabel() }}{{ $taskCreations->count() > 1 ? ' · ' . $taskCreations->count() : '' }}</span>
                            @elseif($image)
                                <span class="badge badge-purple">Aprovada</span>
                            @else
                                <span class="text-xs" style="color:var(--muted)">sem imagem ou vídeo entregue</span>
                            @endif
                            @if($image)
                                <span class="flex gap-1.5">
                                    <a href="{{ route('ad-creations.create', $task) }}" class="btn btn-sm {{ $taskCreations->isEmpty() ? 'btn-primary' : '' }}" title="Criar anúncio na Meta"><x-icon name="upload" size="13" />Meta</a>
                                    @if(isset($googleClients[$task->client_id]))
                                        <a href="{{ route('google-pmax.create', $task) }}" class="btn btn-sm" title="Levar para a Performance Max do Google">Google</a>
                                    @endif
                                </span>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <p class="text-sm col-span-full" style="color:var(--muted)">Nenhuma arte neste filtro.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>

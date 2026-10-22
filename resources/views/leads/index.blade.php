<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">CRM</p>
            <h1 class="text-2xl font-black text-[var(--text)]">Central de Leads</h1>
        </div>
    </x-slot>

    <div class="py-8 px-6">

        @if(session('success'))
            <div class="mb-6 px-4 py-3 text-sm border" style="background: rgba(52,211,153,.06); border-color: rgba(52,211,153,.25); color: #34d399;">
                {{ session('success') }}
            </div>
        @endif

        @include('leads._filters', [
            'view'             => $view,
            'indexRoute'       => 'leads.index',
            'resultsRoute'     => 'leads.results',
            'resultsTarget'    => '#leads-results',
            'showClientFilter' => true,
            'clients'          => $clients,
            'channels'         => $channels,
            'sources'          => $sources,
        ])

        @if($view === 'lista')
            <div id="leads-results">
                @include('leads._results', [
                    'leads'        => $leads,
                    'showClient'   => true,
                    'showAssignee' => true,
                    'showRoute'    => 'leads.show',
                    'unnotifiedClientIds' => $unnotifiedClientIds,
                ])
            </div>
        @else
            {{-- Quadro no padrão Mirbal (classes .kb-* em app.css) --}}
            <div id="leads-board" class="kb-board" style="min-height:70vh"
                 data-kanban-board data-status-field="stage">

                @foreach(\App\Models\ClientLeadOpportunity::$stages as $key => $s)
                    @php($cards = $board->get($key, collect()))
                    <div class="kb-col" style="--kb-bar: var(--{{ $s['color'] }}); width:280px" data-kanban-column data-status="{{ $key }}">

                        <div class="kb-col-head">
                            <h5 class="card-title truncate">{{ $s['label'] }}</h5>
                            <span class="kb-count" data-kanban-count>{{ $cards->count() }}</span>
                        </div>

                        <div class="kb-list" data-kanban-list>
                            @forelse($cards as $opp)
                                <div class="kb-card cursor-pointer group"
                                     data-kanban-card data-id="{{ $opp->id }}" data-update-url="{{ route('leads.update-stage', $opp) }}">

                                    {{-- Cliente (ou "sem cliente") + canal --}}
                                    <div class="kb-card-top">
                                        @if($opp->lead->client)
                                            <span class="badge tag-purple truncate" style="max-width:170px">{{ $opp->lead->client->displayName() }}</span>
                                            @if(in_array($opp->lead->client_id, $unnotifiedClientIds, true))
                                                @include('leads._no-subscriber-icon')
                                            @endif
                                        @else
                                            <span class="badge tag-orange">sem cliente</span>
                                        @endif
                                        @if($opp->channel)
                                            <span class="badge tag-muted ml-auto">{{ $opp->channel->kindLabel() }}</span>
                                        @endif
                                    </div>

                                    <a href="{{ route('leads.show', $opp) }}" class="kb-card-title group-hover:text-[var(--purple)] transition-colors">
                                        {{ $opp->lead->name ?: 'Sem nome' }}
                                    </a>
                                    <p class="kb-card-desc">{{ $opp->lead->phone ?: $opp->lead->email ?: '—' }}</p>

                                    @if($preview = $opp->messagePreview(90))
                                        <p class="kb-card-desc italic" style="color:var(--muted2)">“{{ $preview }}”</p>
                                    @endif

                                    <div class="kb-card-foot">
                                        <span>{{ $opp->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="kb-empty">Nenhum lead</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach

            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (document.getElementById('leads-board')) {
                    initKanbanDnd('#leads-board');
                }
            });
            </script>
        @endif
    </div>
</x-app-layout>

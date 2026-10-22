<x-portal-layout>
    <x-slot name="title">Central de Leads</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-black" style="color: var(--text)">Central de Leads</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">
            Leads capturados pelo site, Meta e WhatsApp de {{ $client->company_name }}.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 text-sm border rounded-lg" style="background: rgba(52,211,153,.06); border-color: rgba(52,211,153,.25); color: #34d399;">
            {{ session('success') }}
        </div>
    @endif

    @include('leads._filters', [
        'view'             => $view,
        'indexRoute'       => 'portal.leads.index',
        'resultsRoute'     => 'portal.leads.results',
        'resultsTarget'    => '#leads-results',
        'showClientFilter' => false,
        'clients'          => null,
        'channels'         => $channels,
        'sources'          => $sources,
    ])

    @if($view === 'lista')
        <div id="leads-results">
            @include('leads._results', [
                'leads'        => $leads,
                'showClient'   => false,
                'showAssignee' => false,
                'showRoute'    => 'portal.leads.show',
            ])
        </div>
    @else
        {{-- Quadro no padrão Mirbal (classes .kb-* em app.css) --}}
        <div id="leads-board" class="kb-board" style="min-height:60vh"
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
                            <div class="kb-card cursor-pointer"
                                 data-kanban-card data-id="{{ $opp->id }}" data-update-url="{{ route('portal.leads.update-stage', $opp) }}">

                                @if($opp->channel)
                                    <div class="kb-card-top">
                                        <span class="badge tag-muted">{{ $opp->channel->kindLabel() }}</span>
                                    </div>
                                @endif

                                <a href="{{ route('portal.leads.show', $opp) }}" class="kb-card-title hover:underline">
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
</x-portal-layout>

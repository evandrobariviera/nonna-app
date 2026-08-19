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
        <div id="leads-board" class="flex gap-4 overflow-x-auto pb-4" style="min-height:60vh"
             data-kanban-board data-status-field="stage">

            @foreach(\App\Models\ClientLeadOpportunity::$stages as $key => $s)
                @php($cards = $board->get($key, collect()))
                <div class="flex-shrink-0 w-64 flex flex-col gap-3" data-kanban-column data-status="{{ $key }}">

                    <div class="flex items-center justify-between px-3 py-2" style="border-bottom:2px solid var(--{{ $s['color'] }})">
                        <span class="text-xs font-bold uppercase tracking-wider" style="color:var(--{{ $s['color'] }})">
                            {{ $s['label'] }}
                        </span>
                        <span class="text-xs px-1.5 py-0.5 rounded" data-kanban-count
                              style="background:var(--s3); color:var(--muted)">
                            {{ $cards->count() }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-2 flex-1" data-kanban-list>
                        @forelse($cards as $opp)
                            <div class="card p-3 cursor-pointer hover:border-[var(--purple)] transition-colors"
                                 data-kanban-card data-id="{{ $opp->id }}" data-update-url="{{ route('portal.leads.update-stage', $opp) }}">

                                <a href="{{ route('portal.leads.show', $opp) }}"
                                   class="text-sm font-semibold hover:underline" style="color: var(--text)">
                                    {{ $opp->lead->name ?: 'Sem nome' }}
                                </a>

                                <div class="text-xs mt-1 mb-2" style="color: var(--muted)">
                                    {{ $opp->lead->phone ?: $opp->lead->email ?: '—' }}
                                </div>

                                <div class="flex items-center justify-between mt-2">
                                    <span class="text-xs" style="color:var(--muted)">{{ $opp->channel?->kindLabel() ?? '—' }}</span>
                                    <span class="text-xs" style="color:var(--muted)">{{ $opp->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="border border-dashed rounded text-center py-8 text-xs opacity-50" style="border-color: var(--border); color: var(--muted)">
                                Nenhum lead
                            </div>
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

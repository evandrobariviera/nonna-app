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
                ])
            </div>
        @else
            <div id="leads-board" class="flex gap-4 overflow-x-auto pb-4" style="min-height:70vh"
                 data-kanban-board data-status-field="stage">

                @foreach(\App\Models\ClientLeadOpportunity::$stages as $key => $s)
                    @php($cards = $board->get($key, collect()))
                    <div class="flex-shrink-0 w-64 flex flex-col gap-3" data-kanban-column data-status="{{ $key }}">

                        <div class="flex items-center justify-between px-3 py-2" style="border-bottom:2px solid var(--{{ $s['color'] }})">
                            <span class="text-xs font-bold font-mono uppercase tracking-wider" style="color:var(--{{ $s['color'] }})">
                                {{ $s['label'] }}
                            </span>
                            <span class="text-xs font-mono px-1.5 py-0.5 rounded" data-kanban-count
                                  style="background:var(--s3); color:var(--muted)">
                                {{ $cards->count() }}
                            </span>
                        </div>

                        <div class="flex flex-col gap-2 flex-1" data-kanban-list>
                            @forelse($cards as $opp)
                                <div class="card p-3 cursor-pointer hover:border-[var(--purple)] transition-colors group"
                                     data-kanban-card data-id="{{ $opp->id }}" data-update-url="{{ route('leads.update-stage', $opp) }}">

                                    <a href="{{ route('leads.show', $opp) }}"
                                       class="text-sm font-semibold text-[var(--text)] group-hover:text-[var(--purple)] transition-colors leading-tight">
                                        {{ $opp->lead->name ?: 'Sem nome' }}
                                    </a>

                                    <div class="text-xs text-[var(--muted)] mt-1 mb-2">
                                        {{ $opp->lead->phone ?: $opp->lead->email ?: '—' }}
                                    </div>

                                    @if($opp->lead->client)
                                        <div class="text-xs font-semibold mb-1" style="color:var(--purple)">
                                            {{ $opp->lead->client->displayName() }}
                                        </div>
                                    @else
                                        <span class="badge" style="font-size:9px; padding:1px 6px; border-color: var(--orange); color: var(--orange)">
                                            sem cliente
                                        </span>
                                    @endif

                                    <div class="flex items-center justify-between mt-2">
                                        <span class="text-xs" style="color:var(--muted)">
                                            {{ $opp->channel?->kindLabel() ?? '—' }}
                                        </span>
                                        <span class="text-xs font-mono" style="color:var(--muted)">
                                            {{ $opp->created_at->diffForHumans() }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <div class="border border-dashed border-[var(--border)] rounded text-center py-8 text-xs text-[var(--muted)] opacity-50">
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
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">Performance</p>
            <h1 class="text-2xl font-black text-[var(--text)]">Central de Leads</h1>
        </div>
    </x-slot>

    <div class="py-8 px-6">
        @include('performance._tabs')

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

        @if($view === 'resultados')
            @if($funnel)
                @include('leads._funnel', [
                    'funnel'        => $funnel,
                    'campaignRoute' => 'campaigns.show',
                    'ruleUrl'       => route('clients.show', [$client, 'tab' => 'leads']),
                ])
            @else
                @include('leads._funnel-overview', ['overview' => $overview, 'from' => $from, 'to' => $to])
            @endif
        @elseif($view === 'lista')
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
            @include('leads._board', [
                'board'               => $board,
                'showClosed'          => $showClosed,
                'updateStageRoute'    => 'leads.update-stage',
                'showRoute'           => 'leads.show',
                'showClient'          => true,
                'markNonna'           => false,
                'unnotifiedClientIds' => $unnotifiedClientIds,
                'closedUrl'           => route('leads.index', array_merge(request()->query(), ['fechados' => 1])),
            ])
        @endif
    </div>
</x-app-layout>

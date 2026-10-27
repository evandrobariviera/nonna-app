<x-portal-layout>
    <x-slot name="title">Central de Leads</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-black" style="color: var(--text)">Central de Leads</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">
            Leads capturados pelo site, Meta e WhatsApp de {{ $client->company_name }}.
        </p>

        @if($canConfigure)
            <form method="POST" action="{{ route('portal.leads.settings') }}" class="mt-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="hide_assigned" value="0">
                <label class="inline-flex items-start gap-2 text-xs cursor-pointer" style="color: var(--text)">
                    <input type="checkbox" name="hide_assigned" value="1" class="mt-0.5"
                           {{ $hideAssigned ? 'checked' : '' }} onchange="this.form.submit()">
                    <span>
                        Lead atribuído some para os outros
                        <span class="block" style="color: var(--muted)">Cada pessoa da sua equipe vê só os leads dela e os sem responsável. Você, como contato principal, continua vendo todos.</span>
                    </span>
                </label>
            </form>
        @elseif($hideAssigned)
            <p class="text-xs mt-2" style="color: var(--muted)">Você vê os leads que são seus e os que ainda não têm responsável.</p>
        @endif
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
                'showAssignee' => true,
                'markNonna'    => true,
                'showRoute'    => 'portal.leads.show',
            ])
        </div>
    @else
        @include('leads._board', [
            'board'            => $board,
            'showClosed'       => $showClosed,
            'updateStageRoute' => 'portal.leads.update-stage',
            'showRoute'        => 'portal.leads.show',
            'showClient'       => false,
            'markNonna'        => true,
            'closedUrl'        => route('portal.leads.index', array_merge(request()->query(), ['fechados' => 1])),
        ])
    @endif
</x-portal-layout>

{{-- Quadro (Kanban) da Central de Leads, no padrão Mirbal (classes .kb-* em app.css) —
     compartilhado entre o app interno (leads/index) e o Portal (portal/leads/index).
     Espera: $board (cards agrupados por stage), $showClosed, $updateStageRoute, $showRoute,
     $showClient, $markNonna (Portal: responsável da Nonna aparece como "Nonna · Fulano"),
     $unnotifiedClientIds (só interno), $closedUrl (link pra mostrar ganhos e perdidos). --}}
<div id="leads-board" class="kb-board" style="min-height:65vh"
     data-kanban-board data-status-field="stage">

    @foreach(\App\Models\ClientLeadOpportunity::$stages as $key => $s)
        @php
            $cards = $board->get($key, collect());
            $isClosed = in_array($key, \App\Models\ClientLeadOpportunity::CLOSED_STAGES, true);
            $hidden = $isClosed && !$showClosed;
            $total = $cards->sum(fn ($o) => (float) $o->deal_value);
        @endphp
        <div class="kb-col" style="--kb-bar: var(--{{ $s['color'] }}); width:{{ $hidden ? '200px' : '280px' }}" data-kanban-column data-status="{{ $key }}">

            <div class="kb-col-head">
                <h5 class="card-title truncate">{{ $s['label'] }}</h5>
                <span class="kb-count" data-kanban-count>{{ $cards->count() }}</span>
            </div>
            @if($total > 0)
                <div class="text-xs font-semibold px-1 pb-2" style="color:var(--{{ $s['color'] }})">
                    {{ \App\Models\ClientLeadOpportunity::formatMoney($total) }}
                </div>
            @endif

            <div class="kb-list" data-kanban-list>
                @forelse($cards as $opp)
                    <div class="kb-card cursor-pointer group"
                         data-kanban-card data-id="{{ $opp->id }}" data-update-url="{{ route($updateStageRoute, $opp) }}">

                        <div class="kb-card-top">
                            @if($showClient)
                                @if($opp->lead->client)
                                    <span class="badge tag-purple truncate" style="max-width:170px">{{ $opp->lead->client->displayName() }}</span>
                                    @if(in_array($opp->lead->client_id, $unnotifiedClientIds ?? [], true))
                                        @include('leads._no-subscriber-icon')
                                    @endif
                                @else
                                    <span class="badge tag-orange">sem cliente</span>
                                @endif
                            @endif
                            @if($opp->channel)
                                <span class="badge tag-muted ml-auto">{{ $opp->channel->kindLabel() }}</span>
                            @endif
                        </div>

                        <a href="{{ route($showRoute, $opp) }}" class="kb-card-title group-hover:text-[var(--purple)] transition-colors">
                            {{ $opp->lead->name ?: 'Sem nome' }}
                        </a>
                        <p class="kb-card-desc">{{ $opp->lead->phone ?: $opp->lead->email ?: '—' }}</p>

                        @if($preview = $opp->messagePreview(90))
                            <p class="kb-card-desc italic" style="color:var(--muted2)">“{{ $preview }}”</p>
                        @endif

                        @if($opp->deal_value !== null)
                            <p class="text-sm font-bold" style="color:var(--green)">{{ \App\Models\ClientLeadOpportunity::formatMoney($opp->deal_value) }}</p>
                        @endif

                        <div class="kb-card-foot">
                            <span>{{ $opp->created_at->diffForHumans() }}</span>
                            @if($name = $opp->assigneeName($markNonna))
                                <span class="ml-auto inline-flex items-center gap-1 truncate" style="max-width:150px" title="Responsável: {{ $name }}">
                                    <x-icon name="user" size="12" /> {{ $name }}
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="kb-empty">
                        @if($hidden)
                            Escondidos · <a href="{{ $closedUrl }}" class="underline">mostrar</a>
                        @else
                            Nenhum lead
                        @endif
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

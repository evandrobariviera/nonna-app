{{-- Seção "Estratégia" do dashboard — macroplanejamentos + reuniões em acompanhamento.
     Cards no padrão <x-item-card>: cor do ícone = natureza, barrinha = estado. --}}
<div class="auto-grid mb-4">

    {{-- Clientes sem planejamento ativo --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <x-icon name="flag" size="14" />
            Sem planejamento ativo ({{ $clientsWithoutActivePlan->count() }})
        </h4>
        @if($clientsWithoutActivePlan->isEmpty())
            <p class="text-xs flex items-center gap-1.5" style="color:var(--muted)">
                <x-icon name="party-popper" size="13" />
                Todos os clientes ativos têm um ciclo em execução.
            </p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($clientsWithoutActivePlan as $client)
                    <x-item-card kind="cliente" :title="$client->displayName()" state="late"
                                 :href="route('clients.show', [$client, 'tab' => 'planejamentos'])">
                        <x-slot:meta><span style="color:var(--red)">sem ciclo em execução</span></x-slot:meta>
                    </x-item-card>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Planejamentos vencendo em até 30 dias --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <x-icon name="alarm-clock" size="14" />
            Vencendo em até 30 dias ({{ $plansExpiringSoon->count() }})
        </h4>
        @if($plansExpiringSoon->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhum planejamento vencendo em breve.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($plansExpiringSoon as $plan)
                    @php $daysLeft = today()->diffInDays($plan->period_end, false); @endphp
                    <x-item-card kind="planejamento" :title="$plan->client?->displayName() ?? '—'" state="soon"
                                 :href="route('macroplans.edit', $plan)">
                        <x-slot:meta>
                            <span class="font-semibold" style="color:var(--orange)">{{ $daysLeft <= 0 ? 'vence hoje' : $daysLeft . ' dia' . ($daysLeft !== 1 ? 's' : '') }} · {{ $plan->period_end->format('d/m') }}</span>
                        </x-slot:meta>
                    </x-item-card>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Planejamentos ativos --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <span class="h-2 w-2 rounded-full" style="background:var(--green)"></span>
            Planejamentos ativos ({{ $activePlans->count() }})
        </h4>
        @if($activePlans->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhum planejamento em execução no momento.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($activePlans as $plan)
                    <x-item-card kind="planejamento" :title="$plan->client?->displayName() ?? '—'"
                                 :href="route('macroplans.edit', $plan)">
                        <x-slot:meta><span>{{ $plan->periodLabel() }}</span></x-slot:meta>
                    </x-item-card>
                @endforeach
            </div>
        @endif
    </div>

</div>

<div class="auto-grid">

    {{-- Reuniões em Pós-Reunião --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <x-icon name="file-text" size="14" />
            Em Pós-Reunião ({{ $meetingsPosReuniao->count() }})
        </h4>
        @if($meetingsPosReuniao->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhuma reunião aguardando ata/follow-up.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($meetingsPosReuniao as $meeting)
                    @php [$mState, $mHint] = $meeting->cardState(); @endphp
                    <x-item-card kind="reuniao" :icon="$meeting->typeIcon()" :title="$meeting->title" :state="$mState"
                                 :href="route('meetings.show', $meeting)">
                        <x-slot:meta>
                            <span>{{ $meeting->scheduled_at->format('d/m') }}</span>
                            @if($mHint)<span class="font-semibold" style="color:{{ $mState === 'late' ? 'var(--red)' : 'var(--orange)' }}">{{ $mHint }}</span>@endif
                            <span class="truncate">{{ $meeting->client?->displayName() ?? '—' }}</span>
                            <span class="inline-flex gap-1"><x-meeting-checks :meeting="$meeting" /></span>
                        </x-slot:meta>
                    </x-item-card>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Reuniões Realizadas --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3 flex items-center gap-1.5" style="color:var(--text)">
            <x-icon name="circle-check" size="14" />
            Finalizadas recentemente ({{ $meetingsRealizadas->count() }})
        </h4>
        @if($meetingsRealizadas->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhuma reunião finalizada recentemente.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($meetingsRealizadas as $meeting)
                    <x-item-card kind="reuniao" :icon="$meeting->typeIcon()" :title="$meeting->title"
                                 :href="route('meetings.show', $meeting)">
                        <x-slot:meta>
                            <span class="truncate">{{ $meeting->client?->displayName() ?? '—' }}</span>
                            <span>{{ $meeting->scheduled_at->format('d/m') }}</span>
                            <span class="inline-flex gap-1"><x-meeting-checks :meeting="$meeting" /></span>
                        </x-slot:meta>
                    </x-item-card>
                @endforeach
            </div>
        @endif
    </div>

</div>

<div class="mt-4 flex items-center gap-3">
    <a href="{{ route('macroplans.index') }}" class="text-xs font-mono" style="color:var(--purple)">Ver todos os planejamentos →</a>
    <a href="{{ route('meetings.index') }}" class="text-xs font-mono" style="color:var(--purple)">Ver todas as reuniões →</a>
</div>

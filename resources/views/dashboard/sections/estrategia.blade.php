{{-- Seção "Estratégia" do dashboard — macroplanejamentos + reuniões em acompanhamento --}}
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
                    <a href="{{ route('clients.show', [$client, 'tab' => 'planejamentos']) }}"
                       class="flex items-center gap-2 px-3 py-2 transition-colors" style="background:var(--s2); border-left:2px solid var(--red)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <x-icon-chip icon="building-2" :color="$client->statusColor()" size="24" />
                        <p class="text-xs font-semibold" style="color:var(--text)">{{ $client->displayName() }}</p>
                    </a>
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
                    <a href="{{ route('macroplans.edit', $plan) }}"
                       class="flex items-center gap-2 px-3 py-2 transition-colors" style="background:var(--s2); border-left:2px solid var(--orange)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <x-icon-chip icon="compass" :color="$plan->statusColor()" size="24" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold" style="color:var(--text)">{{ $plan->client?->displayName() ?? '—' }}</p>
                            <p class="text-xs font-mono mt-0.5" style="color:var(--orange)">
                                {{ $daysLeft <= 0 ? 'vence hoje' : $daysLeft . ' dia' . ($daysLeft !== 1 ? 's' : '') }} · {{ $plan->period_end->format('d/m') }}
                            </p>
                        </div>
                    </a>
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
                    <a href="{{ route('macroplans.edit', $plan) }}"
                       class="flex items-center gap-2 px-3 py-2 transition-colors" style="background:var(--s2)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <x-icon-chip icon="compass" :color="$plan->statusColor()" size="24" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold" style="color:var(--text)">{{ $plan->client?->displayName() ?? '—' }}</p>
                            <p class="text-xs font-mono mt-0.5" style="color:var(--muted)">{{ $plan->periodLabel() }}</p>
                        </div>
                    </a>
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
                    <a href="{{ route('meetings.show', $meeting) }}"
                       class="flex items-center gap-2 px-3 py-2 transition-colors" style="background:var(--s2); border-left:2px solid var(--orange)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <x-icon-chip :icon="$meeting->typeIcon()" :color="$meeting->statusColor()" size="24" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold" style="color:var(--text)">{{ $meeting->title }}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $meeting->client?->displayName() ?? '—' }}</span>
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $meeting->scheduled_at->format('d/m') }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Reuniões Realizadas --}}
    <div class="card px-5 py-4">
        <h4 class="text-sm font-bold mb-3" style="color:var(--text)">
            ✔ Realizadas recentemente ({{ $meetingsRealizadas->count() }})
        </h4>
        @if($meetingsRealizadas->isEmpty())
            <p class="text-xs" style="color:var(--muted)">Nenhuma reunião realizada recentemente.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($meetingsRealizadas as $meeting)
                    <a href="{{ route('meetings.show', $meeting) }}"
                       class="flex items-center gap-2 px-3 py-2 transition-colors" style="background:var(--s2)"
                       onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        <x-icon-chip :icon="$meeting->typeIcon()" :color="$meeting->statusColor()" size="24" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold" style="color:var(--text)">{{ $meeting->title }}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $meeting->client?->displayName() ?? '—' }}</span>
                                <span class="text-xs font-mono" style="color:var(--muted)">{{ $meeting->scheduled_at->format('d/m') }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

</div>

<div class="mt-4 flex items-center gap-3">
    <a href="{{ route('macroplans.index') }}" class="text-xs font-mono" style="color:var(--purple)">Ver todos os planejamentos →</a>
    <a href="{{ route('meetings.index') }}" class="text-xs font-mono" style="color:var(--purple)">Ver todas as reuniões →</a>
</div>

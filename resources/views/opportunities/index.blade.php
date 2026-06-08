<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">CRM</p>
                <h1 class="text-2xl font-black text-[var(--text)]">Oportunidades</h1>
            </div>
            <a href="{{ route('opportunities.create') }}"
               class="flex items-center gap-2 px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
               style="background: var(--purple);">
                + Nova Oportunidade
            </a>
        </div>
    </x-slot>

    <div class="py-8 px-6" x-data="kanban()">

        @if(session('success'))
            <div class="mb-6 px-4 py-3 text-sm border" style="background: rgba(52,211,153,.06); border-color: rgba(52,211,153,.25); color: #34d399;">
                {{ session('success') }}
            </div>
        @endif

        {{-- Kanban Board --}}
        <div class="flex gap-4 overflow-x-auto pb-4" style="min-height: 70vh;">

            @php
                $stageColors = [
                    'novo_lead'           => 'var(--muted)',
                    'qualificacao'        => 'var(--purple)',
                    'diagnostico_reuniao' => 'var(--purple)',
                    'proposta_enviada'    => 'var(--orange)',
                    'negociando'          => 'var(--orange)',
                ];
                $openStages = ['novo_lead', 'qualificacao', 'diagnostico_reuniao', 'proposta_enviada', 'negociando'];
            @endphp

            @foreach($openStages as $stage)
                @php
                    $stageCards = $opportunities->get($stage, collect());
                    $stageMeta  = \App\Models\Opportunity::$stages[$stage];
                @endphp
                <div class="flex-shrink-0 w-64 flex flex-col gap-3">

                    {{-- Column Header --}}
                    <div class="flex items-center justify-between px-3 py-2 border-b"
                         style="border-color: {{ $stageColors[$stage] ?? 'var(--border2)' }}; border-bottom-width: 2px;">
                        <span class="text-xs font-bold font-mono uppercase tracking-wider"
                              style="color: {{ $stageColors[$stage] ?? 'var(--muted2)' }}">
                            {{ $stageMeta['label'] }}
                        </span>
                        <span class="text-xs font-mono px-1.5 py-0.5 rounded"
                              style="background: var(--s3); color: var(--muted);">
                            {{ $stageCards->count() }}
                        </span>
                    </div>

                    {{-- Cards --}}
                    <div class="flex flex-col gap-2 flex-1">
                        @forelse($stageCards as $opp)
                            <div class="card p-3 cursor-pointer hover:border-[var(--purple)] transition-colors group"
                                 x-data="{ open: false }">

                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <a href="{{ route('opportunities.show', $opp) }}"
                                       class="text-sm font-semibold text-[var(--text)] group-hover:text-[var(--purple)] transition-colors leading-tight">
                                        {{ $opp->title }}
                                    </a>
                                </div>

                                <div class="text-xs text-[var(--muted)] mb-3">
                                    {{ $opp->contact->name }}
                                    @if($opp->contact->company_name)
                                        · {{ $opp->contact->company_name }}
                                    @endif
                                </div>

                                @if($opp->proposed_fee)
                                    <div class="text-xs font-mono text-[var(--green)] mb-2">
                                        R$ {{ number_format($opp->proposed_fee, 0, ',', '.') }}/mês
                                    </div>
                                @endif

                                @if($opp->services_interest)
                                    <div class="flex flex-wrap gap-1 mb-3">
                                        @foreach(array_slice($opp->services_interest, 0, 3) as $svc)
                                            <span class="badge" style="font-size: 9px; padding: 1px 6px;">
                                                {{ \App\Models\Client::$services[$svc] ?? $svc }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Stage move buttons --}}
                                <div class="flex items-center gap-1 mt-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    @if($stage !== 'novo_lead')
                                        @php
                                            $prevStage = $openStages[array_search($stage, $openStages) - 1];
                                        @endphp
                                        <button onclick="moveStage('{{ $opp->id }}', '{{ $prevStage }}')"
                                                class="text-xs font-mono px-2 py-1 border border-[var(--border2)] text-[var(--muted)] hover:text-[var(--text)] hover:border-[var(--purple)] transition-colors">
                                            ← Voltar
                                        </button>
                                    @endif

                                    @if($stage !== 'negociando')
                                        @php
                                            $nextStage = $openStages[array_search($stage, $openStages) + 1];
                                        @endphp
                                        <button onclick="moveStage('{{ $opp->id }}', '{{ $nextStage }}')"
                                                class="text-xs font-mono px-2 py-1 border border-[var(--border2)] text-[var(--muted)] hover:text-[var(--text)] hover:border-[var(--purple)] transition-colors">
                                            Avançar →
                                        </button>
                                    @endif
                                </div>

                                <div class="text-xs text-[var(--muted)] font-mono mt-2">
                                    {{ $opp->created_at->diffForHumans() }}
                                </div>
                            </div>
                        @empty
                            <div class="border border-dashed border-[var(--border)] rounded text-center py-8 text-xs text-[var(--muted)] opacity-50">
                                Nenhuma oportunidade
                            </div>
                        @endforelse
                    </div>

                    {{-- Ganhar / Perder (only for last open stage) --}}
                    @if($stage === 'negociando')
                        @foreach($stageCards as $opp)
                            {{-- rendered inline above --}}
                        @endforeach
                    @endif

                </div>
            @endforeach

        </div>

        {{-- Ganhas / Perdidas recentes --}}
        @if($closed->isNotEmpty())
            <div class="mt-10">
                <h2 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-4">Encerradas recentemente</h2>
                <div class="card">
                    <table class="nonna-table">
                        <thead>
                            <tr>
                                <th>Oportunidade</th>
                                <th>Contato</th>
                                <th>Resultado</th>
                                <th>Valor</th>
                                <th>Encerrada</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($closed as $opp)
                                <tr>
                                    <td class="font-semibold text-[var(--text)]">{{ $opp->title }}</td>
                                    <td class="text-sm text-[var(--muted2)]">{{ $opp->contact->name }}</td>
                                    <td>
                                        @if($opp->stage === 'ganho')
                                            <span class="badge badge-green">Ganho</span>
                                        @else
                                            <span class="badge badge-red">Perdido</span>
                                            @if($opp->lost_reason)
                                                <div class="text-xs text-[var(--muted)] mt-0.5">{{ $opp->lost_reason }}</div>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-sm font-mono text-[var(--muted2)]">
                                        {{ $opp->proposed_fee ? 'R$ ' . number_format($opp->proposed_fee, 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="text-xs font-mono text-[var(--muted)]">
                                        {{ ($opp->won_at ?? $opp->lost_at)?->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td>
                                        @if($opp->stage === 'ganho' && $opp->client_id)
                                            <a href="{{ route('clients.show', $opp->client_id) }}"
                                               class="text-xs font-mono text-[var(--purple)] hover:text-[var(--text)] transition-colors">
                                                Ver Cliente →
                                            </a>
                                        @else
                                            <a href="{{ route('opportunities.show', $opp) }}"
                                               class="text-xs font-mono text-[var(--muted)] hover:text-[var(--text)] transition-colors">
                                                Ver →
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>

    <script>
    function kanban() {
        return {};
    }

    function moveStage(id, stage) {
        fetch(`/oportunidades/${id}/stage`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({ stage }),
        }).then(r => r.json()).then(data => {
            if (data.ok) window.location.reload();
        });
    }
    </script>
</x-app-layout>

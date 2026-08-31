<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">Gestão de Clientes</p>
            <h1 class="text-2xl font-black text-[var(--text)]">Entrada — Onboarding</h1>
        </div>
    </x-slot>

    <div class="py-8 px-6 space-y-10">

        @if(session('success'))
            <div class="px-4 py-3 text-sm border" style="background: rgba(52,211,153,.06); border-color: rgba(52,211,153,.25); color: #34d399;">
                {{ session('success') }}
            </div>
        @endif

        {{-- EM ANDAMENTO --}}
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)]">
                    Em andamento ({{ $emAndamento->count() }})
                </h2>
            </div>

            @if($emAndamento->isEmpty())
                <div class="tab-placeholder">
                    <div class="tab-placeholder-icon"><x-icon name="list-checks" size="32" /></div>
                    <div class="tab-placeholder-title">Nenhum cliente em onboarding</div>
                    <div class="tab-placeholder-desc">A esteira começa sozinha quando uma oportunidade "Novo Cliente" é fechada como ganha.</div>
                </div>
            @else
                <div class="card">
                    <table class="nonna-table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th style="width:200px">Fase</th>
                                <th style="width:160px">Progresso</th>
                                <th>Responsável</th>
                                <th style="width:130px">Última mudança</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($emAndamento as $o)
                                <tr>
                                    <td class="font-semibold text-[var(--text)]">
                                        <a href="{{ route('clients.show', $o->client) }}?tab=onboarding"
                                           class="hover:text-[var(--purple)] transition-colors">
                                            {{ $o->client?->displayName() ?? '—' }}
                                        </a>
                                    </td>
                                    <td class="text-sm text-[var(--muted2)]">{{ $o->phaseLabel() }}</td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 h-1.5 rounded" style="background: var(--s3);">
                                                <div class="h-1.5 rounded" style="background: var(--green); width: {{ $o->progressPercent() }}%;"></div>
                                            </div>
                                            <span class="text-xs font-mono text-[var(--muted)]">{{ $o->progressPercent() }}%</span>
                                        </div>
                                    </td>
                                    <td class="text-sm text-[var(--muted2)]">{{ $o->responsible?->name ?? '—' }}</td>
                                    <td class="text-xs font-mono text-[var(--muted)]">{{ $o->updated_at->diffForHumans() }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('clients.show', $o->client) }}?tab=onboarding" class="btn btn-ghost btn-xs">
                                            Abrir →
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- CONCLUÍDOS --}}
        @if($concluidos->isNotEmpty())
            <div>
                <h2 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-4">Concluídos recentemente</h2>
                <div class="card">
                    <table class="nonna-table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Responsável</th>
                                <th style="width:130px">Concluído em</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($concluidos as $o)
                                <tr>
                                    <td class="font-semibold text-[var(--text)]">{{ $o->client?->displayName() ?? '—' }}</td>
                                    <td class="text-sm text-[var(--muted2)]">{{ $o->responsible?->name ?? '—' }}</td>
                                    <td class="text-xs font-mono text-[var(--muted)]">{{ $o->completed_at->format('d/m/Y') }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('clients.show', $o->client) }}?tab=onboarding" class="btn btn-ghost btn-xs">
                                            Ver →
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</x-app-layout>

{{-- Entrada (Onboarding) e Saída (Offboarding) — desde 2026-10-28 listam os PROJETOS
     (fases + tarefas). $kind = onboarding|offboarding; $emAndamento / $concluidos:
     [project, timeline (ProjectTimeline)]. --}}
@php
    $isOff = ($kind ?? 'onboarding') === 'offboarding';
    $phaseHex = fn (?string $status) => \App\Models\Task::colorHex($status ? \App\Models\ProjectPhase::statusColor($status) : 'muted');
    $projUrl  = fn ($p) => $p->macro_plan_id ? route('macroplans.projects.show', [$p->macro_plan_id, $p]) : route('projects.showDirect', $p);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">Gestão de Clientes</p>
            <h1 class="text-2xl font-black text-[var(--text)]">{{ $isOff ? 'Saída — Offboarding' : 'Entrada — Onboarding' }}</h1>
        </div>
    </x-slot>

    <div class="py-8 px-6 space-y-10">

        @if(session('success'))
            <div class="px-4 py-3 text-sm border" style="background: rgba(52,211,153,.06); border-color: rgba(52,211,153,.25); color: #34d399;">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="px-4 py-3 text-sm border" style="background: rgba(239,68,68,.06); border-color: rgba(239,68,68,.25); color: var(--red);">{{ session('error') }}</div>
        @endif

        <p class="text-sm" style="color:var(--muted2)">
            @if($isOff)
                A saída de um cliente é o projeto <strong>Offboarding</strong>: financeiro (cobranças, cancelar cobranças futuras, distrato),
                encerrar campanhas e projetos, sair das contas e, por último, inativar o cliente. Pra iniciar, abra o cliente →
                aba <strong>Onboarding / Saída</strong> → <strong>Iniciar offboarding</strong>.
            @else
                Cada cliente novo nasce com o projeto <strong>Onboarding</strong> dentro do primeiro planejamento de 90 dias.
                Aqui ficam todos os onboardings: em que fase estão e o que está atrasado. Os passos automáticos (boas-vindas, cadastro,
                contrato, Drive, grupo de WhatsApp) concluem as tarefas sozinhos.
            @endif
        </p>

        {{-- EM ANDAMENTO --}}
        <div>
            <h2 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-4">Em andamento ({{ $emAndamento->count() }})</h2>

            @if($emAndamento->isEmpty())
                <div class="tab-placeholder">
                    <div class="tab-placeholder-icon"><x-icon name="list-checks" size="32" /></div>
                    <div class="tab-placeholder-title">{{ $isOff ? 'Nenhum cliente saindo' : 'Nenhum cliente em onboarding' }}</div>
                    <div class="tab-placeholder-desc">{{ $isOff ? 'O offboarding começa pelo botão "Iniciar offboarding" na página do cliente.' : 'O projeto Onboarding nasce sozinho quando um cliente Ativo é cadastrado.' }}</div>
                </div>
            @else
                <div class="card overflow-x-auto">
                    <table class="nonna-table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th style="width:260px">Fase atual</th>
                                <th style="width:170px">Tarefas</th>
                                <th style="width:120px">Fases</th>
                                <th style="width:110px">Desde</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($emAndamento as $r)
                                @php
                                    $p = $r['project']; $tl = $r['timeline'];
                                    $total = $tl['rows']->sum('total') + $tl['unphased']->count();
                                    $done  = $tl['rows']->sum('done') + $tl['unphased']->where('status', 'concluido')->count();
                                    $pct   = $total ? (int) round($done / $total * 100) : 0;
                                @endphp
                                <tr>
                                    <td class="font-semibold text-[var(--text)]">
                                        <a href="{{ route('clients.show', $p->client) }}" class="hover:text-[var(--purple)] transition-colors">{{ $p->client?->displayName() ?? '—' }}</a>
                                        @if($p->client?->status === 'inactive')<span class="text-xs font-normal" style="color:var(--muted)"> · inativo</span>@endif
                                    </td>
                                    <td class="text-sm">
                                        @if($tl['current'])
                                            <span style="color:{{ $phaseHex($tl['current']['status']) }}">{{ $tl['current']['phase']->name }}</span>
                                        @else
                                            <span style="color:var(--muted)">{{ $tl['phases'] ? 'todas concluídas' : 'sem fases' }}</span>
                                        @endif
                                        @if($tl['late'])
                                            <span class="text-xs font-semibold ml-1" style="color:var(--red)">· {{ $tl['late'] }} atrasada(s)</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 h-1.5 rounded" style="background: var(--s3);">
                                                <div class="h-1.5 rounded" style="background: var(--green); width: {{ $pct }}%;"></div>
                                            </div>
                                            <span class="text-xs font-mono text-[var(--muted)]">{{ $done }}/{{ $total }}</span>
                                        </div>
                                    </td>
                                    <td class="text-xs font-mono text-[var(--muted)]">{{ $tl['done'] }}/{{ $tl['phases'] }}</td>
                                    <td class="text-xs font-mono text-[var(--muted)]">{{ ($p->start_date ?? $p->created_at)->format('d/m/Y') }}</td>
                                    <td class="text-right">
                                        <a href="{{ $projUrl($p) }}" class="btn btn-ghost btn-xs">Abrir →</a>
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
                <div class="card overflow-x-auto">
                    <table class="nonna-table">
                        <thead>
                            <tr><th>Cliente</th><th style="width:130px">Concluído em</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach($concluidos as $r)
                                @php $p = $r['project']; @endphp
                                <tr>
                                    <td class="font-semibold text-[var(--text)]">{{ $p->client?->displayName() ?? '—' }}</td>
                                    <td class="text-xs font-mono text-[var(--muted)]">{{ $p->updated_at->format('d/m/Y') }}</td>
                                    <td class="text-right"><a href="{{ $projUrl($p) }}" class="btn btn-ghost btn-xs">Ver →</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</x-app-layout>

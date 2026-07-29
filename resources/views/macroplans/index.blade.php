<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Fluxo de Trabalho</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Planejamentos</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('macroplans.import.create') }}" class="btn btn-ghost">
                    Importar HTML
                </a>
                <a href="{{ route('macroplans.create') }}" class="btn btn-primary">
                    + Novo Planejamento
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filtros --}}
    <form method="GET" action="{{ route('macroplans.index') }}" class="flex flex-wrap items-center gap-3 mb-5">
        <select name="status"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os status</option>
            @foreach(\App\Models\MacroPlan::$statuses as $key => $s)
                <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
            @endforeach
        </select>

        <select name="client_id"
            style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ request('client_id') === $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn btn-ghost btn-sm">
            Filtrar
        </button>

        @if(request()->hasAny(['status','client_id','mostrar_concluidos']))
            <a href="{{ route('macroplans.index') }}" class="btn btn-ghost btn-sm">
                Limpar
            </a>
        @endif

        @php
            $toggleParams = request()->except('mostrar_concluidos', 'page');
            if (!request()->boolean('mostrar_concluidos')) $toggleParams['mostrar_concluidos'] = '1';
        @endphp
        <a href="{{ route('macroplans.index', $toggleParams) }}"
           class="flex items-center gap-1.5 text-xs font-mono px-3 py-1.5 transition-all"
           style="border:1px solid var(--border2); color:{{ request()->boolean('mostrar_concluidos') ? 'var(--purple)' : 'var(--muted)' }}">
            {{ request()->boolean('mostrar_concluidos') ? '⊙ Ocultar encerrados' : '○ Mostrar encerrados' }}
        </a>
    </form>

    <div class="card">
        @if($macroplans->isEmpty())
            <div class="px-6 py-16 text-center" style="color:var(--muted)">
                <p class="text-sm mb-3">Nenhum planejamento encontrado.</p>
                <a href="{{ route('macroplans.create') }}" class="text-sm font-semibold" style="color:var(--purple)">
                    Criar primeiro planejamento →
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="nonna-table">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Cliente</th>
                            <th>Período</th>
                            <th>Projetos</th>
                            <th>Responsável</th>
                            <th style="width:120px">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($macroplans as $plan)
                            <tr>
                                <td>
                                    <a href="{{ route('macroplans.edit', $plan) }}"
                                       class="font-semibold text-sm hover:underline" style="color:var(--text)">
                                        {{ $plan->title }}
                                    </a>
                                    @if($plan->isLaunched())
                                        <span class="badge badge-green ml-1">ClickUp</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('clients.show', $plan->client) }}"
                                       class="text-xs font-semibold hover:underline" style="color:var(--purple)">
                                        {{ $plan->client->company_name }}
                                    </a>
                                </td>
                                <td class="text-xs font-mono whitespace-nowrap" style="color:var(--muted2)">
                                    {{ $plan->period_start->format('d/m/Y') }}<br>
                                    {{ $plan->period_end->format('d/m/Y') }}
                                </td>
                                <td class="text-sm" style="color:var(--muted2)">
                                    {{ $plan->projects_count ?? $plan->projects->count() }}
                                </td>
                                <td class="text-xs" style="color:var(--muted2)">{{ $plan->responsible->name ?? '—' }}</td>
                                <x-status-dropdown-cell :options="\App\Models\MacroPlan::$statuses" :current="$plan->status"
                                    :action="route('macroplans.update-status', $plan)" :width="120" />
                                <td class="text-right">
                                    <a href="{{ route('macroplans.edit', $plan) }}" class="btn btn-ghost btn-xs">
                                        Editar →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($macroplans->hasPages())
                <div class="px-6 py-4 flex items-center justify-between" style="border-top:1px solid var(--border)">
                    <span class="text-xs font-mono" style="color:var(--muted)">
                        {{ $macroplans->firstItem() }}–{{ $macroplans->lastItem() }} de {{ $macroplans->total() }}
                    </span>
                    <div class="flex gap-2">
                        @if($macroplans->onFirstPage())
                            <span class="badge opacity-30">← Anterior</span>
                        @else
                            <a href="{{ $macroplans->previousPageUrl() }}" class="badge">← Anterior</a>
                        @endif
                        @if($macroplans->hasMorePages())
                            <a href="{{ $macroplans->nextPageUrl() }}" class="badge">Próxima →</a>
                        @else
                            <span class="badge opacity-30">Próxima →</span>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>

</x-app-layout>

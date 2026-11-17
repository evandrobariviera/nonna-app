<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Uso & Custos de IA</span>
    </x-slot>

    <div style="max-width:960px">

        {{-- Filtro de período --}}
        <div class="flex items-center gap-2 mb-6">
            @foreach(['today' => 'Hoje', 'week' => 'Esta semana', 'month' => 'Este mês', 'all' => 'Tudo'] as $key => $label)
                <a href="{{ route('ai.usage.index', ['period' => $key]) }}"
                   class="px-3 py-1.5 rounded text-xs font-semibold transition-colors"
                   style="{{ $period === $key
                       ? 'background:var(--purple); color:#fff'
                       : 'background:var(--s2); color:var(--muted); border:1px solid var(--border2)' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- Cards de totais --}}
        <div class="grid grid-cols-4 gap-4 mb-6">
            @php
                $cards = [
                    ['label' => 'CUSTO ESTIMADO',    'value' => '$' . number_format($totals->total_cost ?? 0, 4),     'color' => 'var(--orange)'],
                    ['label' => 'TOTAL DE TOKENS',   'value' => number_format($totals->total_tokens ?? 0),            'color' => 'var(--purple)'],
                    ['label' => 'TOKENS ENVIADOS',   'value' => number_format($totals->prompt_tokens ?? 0),           'color' => 'var(--muted)'],
                    ['label' => 'TOKENS RECEBIDOS',  'value' => number_format($totals->completion_tokens ?? 0),       'color' => 'var(--muted)'],
                ];
            @endphp
            @foreach($cards as $card)
            <div class="px-4 py-4 rounded" style="background:var(--s2); border:1px solid var(--border2)">
                <div class="text-xs font-semibold mb-1" style="color:var(--muted); font-variant-numeric:tabular-nums; letter-spacing:.05em">{{ $card['label'] }}</div>
                <div class="text-xl font-black" style="color:{{ $card['color'] }}">{{ $card['value'] }}</div>
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-2 gap-5 mb-6">

            {{-- Por agente --}}
            <div style="border:1px solid var(--border2); border-radius:8px; overflow:hidden">
                <div class="px-4 py-3 text-xs font-semibold"
                     style="background:var(--s2); border-bottom:1px solid var(--border2); color:var(--muted); font-variant-numeric:tabular-nums; letter-spacing:.05em">
                    POR AGENTE
                </div>
                <div class="divide-y" style="border-color:var(--border2)">
                    @forelse($byAgent as $row)
                    <div class="flex items-center justify-between px-4 py-2.5">
                        <span class="text-xs font-semibold" style="color:var(--text)">
                            {{ $row->agent?->name ?? 'Sem agente' }}
                        </span>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ number_format($row->tokens) }} tk</span>
                            <span class="text-xs font-bold" style="color:var(--orange)">${{ number_format($row->cost, 4) }}</span>
                        </div>
                    </div>
                    @empty
                        <div class="px-4 py-4 text-xs text-center" style="color:var(--muted)">Sem dados ainda</div>
                    @endforelse
                </div>
            </div>

            {{-- Por modelo --}}
            <div style="border:1px solid var(--border2); border-radius:8px; overflow:hidden">
                <div class="px-4 py-3 text-xs font-semibold"
                     style="background:var(--s2); border-bottom:1px solid var(--border2); color:var(--muted); font-variant-numeric:tabular-nums; letter-spacing:.05em">
                    POR MODELO
                </div>
                <div class="divide-y" style="border-color:var(--border2)">
                    @forelse($byModel as $row)
                    <div class="flex items-center justify-between px-4 py-2.5">
                        <div>
                            <span class="text-xs font-mono font-semibold" style="color:var(--text)">{{ $row->model }}</span>
                            <span class="text-xs ml-1" style="color:var(--muted)">{{ $row->provider }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-mono" style="color:var(--muted)">{{ number_format($row->tokens) }} tk</span>
                            <span class="text-xs font-bold" style="color:var(--orange)">${{ number_format($row->cost, 4) }}</span>
                        </div>
                    </div>
                    @empty
                        <div class="px-4 py-4 text-xs text-center" style="color:var(--muted)">Sem dados ainda</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Créditos do Agente Nonna (AgentCredits): saldo semanal por pessoa; só o chat do agente gasta --}}
        @php $canManageCredits = auth()->user()->can('manage-agent-credits'); @endphp
        <div class="mb-6" style="border:1px solid var(--border2); border-radius:8px; overflow:hidden">
            <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-2"
                 style="background:var(--s2); border-bottom:1px solid var(--border2)">
                <span class="text-xs font-semibold" style="color:var(--muted); letter-spacing:.05em">
                    CRÉDITOS DO AGENTE NONNA — SEMANA DE {{ $creditWeek->format('d/m') }} A {{ $creditWeek->copy()->addDays(6)->format('d/m') }}
                </span>
                <span class="text-[11px]" style="color:var(--muted)">
                    Padrão: {{ number_format(\App\Services\Agent\AgentCredits::DEFAULT_WEEKLY, 0, ',', '.') }} por pessoa · renova toda segunda · só o chat do agente gasta crédito
                </span>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr style="border-bottom:1px solid var(--border2); background:var(--s3)">
                        <th class="px-4 py-2 text-left font-semibold" style="color:var(--muted)">PESSOA</th>
                        <th class="px-4 py-2 text-left font-semibold" style="color:var(--muted); min-width:180px">USADOS</th>
                        <th class="px-4 py-2 text-right font-semibold" style="color:var(--muted)">LIMITE SEMANAL</th>
                        <th class="px-4 py-2 text-right font-semibold" style="color:var(--muted)">EXTRA NA SEMANA</th>
                        <th class="px-4 py-2 text-right font-semibold" style="color:var(--muted)">RESTAM</th>
                        @if($canManageCredits)<th class="px-4 py-2"></th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color:var(--border2)">
                    @foreach($creditRows as $row)
                    @php $barColor = $row['blocked'] ? 'var(--red)' : ($row['warn'] ? 'var(--orange)' : 'var(--purple)'); @endphp
                    <tr x-data="{ edit: false }">
                        <td class="px-4 py-2 font-semibold" style="color:var(--text)">{{ $row['user']->name }}</td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-1.5 rounded-full overflow-hidden" style="background:var(--s3); min-width:80px">
                                    <div class="h-full rounded-full" style="width:{{ $row['pct'] }}%; background:{{ $barColor }}"></div>
                                </div>
                                <span class="font-mono" style="color:var(--muted2)">{{ number_format($row['used'], 0, ',', '.') }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2 text-right font-mono" style="color:var(--text)">
                            {{ number_format($row['limit'], 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-2 text-right font-mono" style="color:var(--muted2)">{{ $row['extra'] ? '+' . number_format($row['extra'], 0, ',', '.') : '—' }}</td>
                        <td class="px-4 py-2 text-right font-mono font-bold" style="color:{{ $row['blocked'] ? 'var(--red)' : ($row['warn'] ? 'var(--orange)' : 'var(--text)') }}">
                            {{ number_format($row['remaining'], 0, ',', '.') }}
                        </td>
                        @if($canManageCredits)
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <button type="button" class="text-[11px] font-semibold" style="color:var(--purple)" x-show="!edit" @click="edit = true">Ajustar</button>
                            <div x-show="edit" x-cloak class="flex flex-col items-end gap-1.5">
                                <form method="POST" action="{{ route('ai.credits.limit', $row['user']) }}" class="flex items-center gap-1.5">
                                    @csrf @method('PATCH')
                                    <span style="color:var(--muted)">Limite semanal</span>
                                    <input type="number" name="weekly_credits" min="0" step="100" value="{{ $row['limit'] }}" class="w-20 text-xs rounded px-2 py-1" style="border:1px solid var(--border2); background:var(--s1); color:var(--text)">
                                    <button class="btn text-[11px] py-1">Salvar</button>
                                </form>
                                <form method="POST" action="{{ route('ai.credits.grant', $row['user']) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    <span style="color:var(--muted)">Extra só nesta semana</span>
                                    <input type="number" name="credits" min="1" step="100" value="500" class="w-20 text-xs rounded px-2 py-1" style="border:1px solid var(--border2); background:var(--s1); color:var(--text)">
                                    <button class="btn btn-primary text-[11px] py-1">Liberar</button>
                                </form>
                                <button type="button" class="text-[11px]" style="color:var(--muted)" @click="edit = false">Fechar</button>
                            </div>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>

        {{-- Histórico recente --}}
        <div style="border:1px solid var(--border2); border-radius:8px; overflow:hidden">
            <div class="px-4 py-3 text-xs font-semibold"
                 style="background:var(--s2); border-bottom:1px solid var(--border2); color:var(--muted); font-variant-numeric:tabular-nums; letter-spacing:.05em">
                HISTÓRICO RECENTE (últimas 50 chamadas)
            </div>
            @if($recent->isEmpty())
                <div class="px-4 py-8 text-xs text-center" style="color:var(--muted)">Nenhuma chamada registrada ainda.</div>
            @else
            <table class="w-full text-xs">
                <thead>
                    <tr style="border-bottom:1px solid var(--border2); background:var(--s3)">
                        <th class="px-4 py-2 text-left font-semibold" style="color:var(--muted)">DATA</th>
                        <th class="px-4 py-2 text-left font-semibold" style="color:var(--muted)">AGENTE</th>
                        <th class="px-4 py-2 text-left font-semibold" style="color:var(--muted)">MODELO</th>
                        <th class="px-4 py-2 text-left font-semibold" style="color:var(--muted)">TRIGGER</th>
                        <th class="px-4 py-2 text-right font-semibold" style="color:var(--muted)">TOKENS</th>
                        <th class="px-4 py-2 text-right font-semibold" style="color:var(--muted)">CUSTO</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color:var(--border2)">
                    @foreach($recent as $row)
                    <tr>
                        <td class="px-4 py-2 font-mono" style="color:var(--muted)">{{ $row->created_at->format('d/m H:i') }}</td>
                        <td class="px-4 py-2" style="color:var(--text)">{{ $row->agent?->name ?? '—' }}</td>
                        <td class="px-4 py-2 font-mono" style="color:var(--muted)">{{ $row->model }}</td>
                        <td class="px-4 py-2">
                            @if($row->trigger)
                                <span class="px-1.5 py-px rounded" style="background:var(--s3); color:var(--muted2)">{{ $row->trigger }}</span>
                            @else
                                <span style="color:var(--muted)">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right font-mono" style="color:var(--text)">{{ number_format($row->total_tokens) }}</td>
                        <td class="px-4 py-2 text-right font-bold" style="color:var(--orange)">${{ number_format($row->estimated_cost_usd, 5) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</x-app-layout>

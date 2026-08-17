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
                <div class="text-xs font-semibold mb-1" style="color:var(--muted); font-family:Arial,'Segoe UI',Tahoma,sans-serif; letter-spacing:.05em">{{ $card['label'] }}</div>
                <div class="text-xl font-black" style="color:{{ $card['color'] }}">{{ $card['value'] }}</div>
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-2 gap-5 mb-6">

            {{-- Por agente --}}
            <div style="border:1px solid var(--border2); border-radius:8px; overflow:hidden">
                <div class="px-4 py-3 text-xs font-semibold"
                     style="background:var(--s2); border-bottom:1px solid var(--border2); color:var(--muted); font-family:Arial,'Segoe UI',Tahoma,sans-serif; letter-spacing:.05em">
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
                     style="background:var(--s2); border-bottom:1px solid var(--border2); color:var(--muted); font-family:Arial,'Segoe UI',Tahoma,sans-serif; letter-spacing:.05em">
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

        {{-- Histórico recente --}}
        <div style="border:1px solid var(--border2); border-radius:8px; overflow:hidden">
            <div class="px-4 py-3 text-xs font-semibold"
                 style="background:var(--s2); border-bottom:1px solid var(--border2); color:var(--muted); font-family:Arial,'Segoe UI',Tahoma,sans-serif; letter-spacing:.05em">
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
